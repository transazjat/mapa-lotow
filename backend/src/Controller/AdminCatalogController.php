<?php

declare(strict_types=1);

namespace Transazja\MapaLotowApi\Controller;

use DateTimeZone;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Transazja\MapaLotowApi\Security\AuthService;
use Transazja\MapaLotowApi\Service\AdminAuditService;

final class AdminCatalogController
{
    private const CONFIG = [
        'airports' => [
            'table' => 'ml_airports',
            'entity' => 'airport',
            'search' => ['name','iata_code','icao_code','city','country_name'],
            'editable' => ['name','iata_code','icao_code','city','country_id','country_name','latitude','longitude','timezone_name'],
        ],
        'airlines' => [
            'table' => 'ml_airlines',
            'entity' => 'airline',
            'search' => ['name','iata_code','icao_code','callsign','country_name'],
            'editable' => ['name','iata_code','icao_code','callsign','country_id','country_name','is_active'],
        ],
        'aircraft' => [
            'table' => 'ml_aircraft_types',
            'entity' => 'aircraft_type',
            'search' => ['name','manufacturer','model','variant','family'],
            'editable' => ['name','manufacturer','model','variant','family','is_active'],
        ],
    ];

    public function __construct(
        private PDO $pdo,
        private AuthService $auth,
        private AdminAuditService $audit
    ) {
    }

    public function list(Request $request, Response $response, array $args): Response
    {
        $adminId=$this->requireAdmin($response);if($adminId instanceof Response)return$adminId;
        $type=(string)($args['type']??'');$config=$this->config($type);if(!$config)return$this->error($response,'Nieznany typ słownika.',404);
        $table=$config['table'];$columns=$this->columns($table);$q=$request->getQueryParams();$search=trim((string)($q['q']??''));$page=max(1,(int)($q['page']??1));$perPage=$this->perPage($q['per_page']??25);$sort=(string)($q['sort']??'name_asc');
        $where=[];$params=[];
        if($search!==''){
            $ors=[];$like='%'.$search.'%';$i=0;
            foreach($config['search'] as $c){
                if(!isset($columns[$c]))continue;
                $key='search_'.$i++;
                $ors[]="t.{$c} LIKE :{$key}";
                $params[$key]=$like;
            }
            if($ors)$where[]='('.implode(' OR ',$ors).')';
        }
        $whereSql=$where?'WHERE '.implode(' AND ',$where):'';
        $count=$this->pdo->prepare("SELECT COUNT(*) FROM {$table} t {$whereSql}");$this->bind($count,$params);$count->execute();$total=(int)$count->fetchColumn();$offset=($page-1)*$perPage;
        $select=['t.*'];
        if($type==='airports')$select[]='(SELECT COUNT(*) FROM ml_flights f WHERE f.departure_airport_id=t.id OR f.arrival_airport_id=t.id) usage_count';
        if($type==='airlines')$select[]='(SELECT COUNT(*) FROM ml_flights f WHERE f.airline_id=t.id) usage_count';
        if($type==='aircraft')$select[]='(SELECT COUNT(*) FROM ml_flights f WHERE f.aircraft_type_id=t.id) usage_count';
        if(isset($columns['country_id']))$select[]='c.name country_canonical';
        $join=isset($columns['country_id'])?' LEFT JOIN ml_countries c ON c.id=t.country_id ':'';
        $orders=[
            'id_asc'=>'t.id ASC','id_desc'=>'t.id DESC',
            'name_asc'=>'t.name ASC,t.id ASC','name_desc'=>'t.name DESC,t.id DESC',
            'iata_asc'=>'t.iata_code ASC,t.id ASC','iata_desc'=>'t.iata_code DESC,t.id DESC',
            'icao_asc'=>'t.icao_code ASC,t.id ASC','icao_desc'=>'t.icao_code DESC,t.id DESC',
            'city_asc'=>'t.city ASC,t.id ASC','city_desc'=>'t.city DESC,t.id DESC',
            'manufacturer_asc'=>'t.manufacturer ASC,t.name ASC','manufacturer_desc'=>'t.manufacturer DESC,t.name ASC',
            'model_asc'=>'t.model ASC,t.variant ASC,t.id ASC','model_desc'=>'t.model DESC,t.variant DESC,t.id DESC',
            'usage_asc'=>'usage_count ASC,t.name ASC','usage_desc'=>'usage_count DESC,t.name ASC'
        ];
        $order=$orders[$sort]??$orders['name_asc'];
        $stmt=$this->pdo->prepare('SELECT '.implode(',',$select)." FROM {$table} t {$join} {$whereSql} ORDER BY {$order} LIMIT :limit OFFSET :offset");$this->bind($stmt,$params);$stmt->bindValue(':limit',$perPage,PDO::PARAM_INT);$stmt->bindValue(':offset',$offset,PDO::PARAM_INT);$stmt->execute();
        $editable=array_values(array_filter($config['editable'],fn($c)=>isset($columns[$c])));
        return$this->json($response,['status'=>'ok','type'=>$type,'page'=>$page,'per_page'=>$perPage,'total'=>$total,'pages'=>max(1,(int)ceil($total/$perPage)),'columns'=>array_keys($columns),'editable_columns'=>$editable,'items'=>$stmt->fetchAll()]);
    }

    public function item(Request $request, Response $response, array $args): Response
    {
        $adminId=$this->requireAdmin($response);if($adminId instanceof Response)return$adminId;$type=(string)($args['type']??'');$config=$this->config($type);if(!$config)return$this->error($response,'Nieznany typ słownika.',404);$id=(int)($args['id']??0);$row=$this->find($config['table'],$id);if(!$row)return$this->error($response,'Nie znaleziono rekordu.',404);
        return$this->json($response,['status'=>'ok','item'=>$row,'columns'=>array_keys($this->columns($config['table'])),'editable_columns'=>array_values(array_filter($config['editable'],fn($c)=>isset($this->columns($config['table'])[$c])))]);
    }

    public function save(Request $request, Response $response, array $args): Response
    {
        $adminId=$this->requireAdmin($response);if($adminId instanceof Response)return$adminId;$type=(string)($args['type']??'');$config=$this->config($type);if(!$config)return$this->error($response,'Nieznany typ słownika.',404);$table=$config['table'];$columns=$this->columns($table);$editable=array_values(array_filter($config['editable'],fn($c)=>isset($columns[$c])));$id=(int)($args['id']??0);$before=$id>0?$this->find($table,$id):false;if($id>0&&!$before)return$this->error($response,'Nie znaleziono rekordu.',404);$data=(array)($request->getParsedBody()?:[]);$values=[];
        foreach($editable as $c){if(array_key_exists($c,$data))$values[$c]=$this->normalize($c,$data[$c]);elseif($before)$values[$c]=$before[$c]??null;}
        if(isset($columns['name'])&&trim((string)($values['name']??''))==='')return$this->error($response,'Nazwa jest wymagana.',422);
        if(isset($values['latitude'])&&$values['latitude']!==null&&((float)$values['latitude'] < -90 || (float)$values['latitude'] > 90))return$this->error($response,'Niepoprawna szerokość geograficzna.',422);
        if(isset($values['longitude'])&&$values['longitude']!==null&&((float)$values['longitude'] < -180 || (float)$values['longitude'] > 180))return$this->error($response,'Niepoprawna długość geograficzna.',422);
        if(isset($values['timezone_name'])&&$values['timezone_name']!==null&&!in_array((string)$values['timezone_name'],DateTimeZone::listIdentifiers(),true))return$this->error($response,'Niepoprawna strefa czasowa IANA.',422);
        if(isset($values['country_id'])&&$values['country_id']!==null){$s=$this->pdo->prepare('SELECT name FROM ml_countries WHERE id=:id');$s->execute(['id'=>$values['country_id']]);$cn=$s->fetchColumn();if(!$cn)return$this->error($response,'Nie znaleziono państwa.',422);if(isset($columns['country_name']))$values['country_name']=$cn;}
        try{$this->validateCodes($response,$table,$id,$values);}catch(\RuntimeException $e){return $this->error($response,$e->getMessage(),409);}
        if($id>0){$set=[];foreach($values as $c=>$v)$set[]="{$c}=:{$c}";$stmt=$this->pdo->prepare("UPDATE {$table} SET ".implode(',',$set).' WHERE id=:id');$stmt->execute($values+['id'=>$id]);$action='catalog.update';$summary='Zmieniono rekord '.$type;}
        else{$cols=array_keys($values);$stmt=$this->pdo->prepare("INSERT INTO {$table} (".implode(',',$cols).') VALUES (:'.implode(',:',$cols).')');$stmt->execute($values);$id=(int)$this->pdo->lastInsertId();$action='catalog.create';$summary='Dodano rekord '.$type;}
        $after=$this->find($table,$id);$this->audit->log($adminId,$action,$config['entity'],$id,$summary,$before?:null,$after?:null,$this->ip($request));return$this->json($response,['status'=>'ok','message'=>'Rekord został zapisany.','item'=>$after]);
    }

    public function countries(Request $request, Response $response): Response
    {
        $adminId=$this->requireAdmin($response);if($adminId instanceof Response)return$adminId;
        $q=trim((string)($request->getQueryParams()['q']??''));
        if(mb_strlen($q,'UTF-8')<1)return$this->json($response,['status'=>'ok','items'=>[]]);

        $polishNames=[
            'AC' => 'Wyspa Wniebowstąpienia',
            'AD' => 'Andora',
            'AE' => 'Zjednoczone Emiraty Arabskie',
            'AF' => 'Afganistan',
            'AG' => 'Antigua i Barbuda',
            'AI' => 'Anguilla',
            'AL' => 'Albania',
            'AM' => 'Armenia',
            'AO' => 'Angola',
            'AQ' => 'Antarktyda',
            'AR' => 'Argentyna',
            'AS' => 'Samoa Amerykańskie',
            'AT' => 'Austria',
            'AU' => 'Australia',
            'AW' => 'Aruba',
            'AX' => 'Wyspy Alandzkie',
            'AZ' => 'Azerbejdżan',
            'BA' => 'Bośnia i Hercegowina',
            'BB' => 'Barbados',
            'BD' => 'Bangladesz',
            'BE' => 'Belgia',
            'BF' => 'Burkina Faso',
            'BG' => 'Bułgaria',
            'BH' => 'Bahrajn',
            'BI' => 'Burundi',
            'BJ' => 'Benin',
            'BL' => 'Saint-Barthélemy',
            'BM' => 'Bermudy',
            'BN' => 'Brunei',
            'BO' => 'Boliwia',
            'BQ' => 'Niderlandy Karaibskie',
            'BR' => 'Brazylia',
            'BS' => 'Bahamy',
            'BT' => 'Bhutan',
            'BV' => 'Wyspa Bouveta',
            'BW' => 'Botswana',
            'BY' => 'Białoruś',
            'BZ' => 'Belize',
            'CA' => 'Kanada',
            'CC' => 'Wyspy Kokosowe',
            'CD' => 'Demokratyczna Republika Konga',
            'CF' => 'Republika Środkowoafrykańska',
            'CG' => 'Kongo',
            'CH' => 'Szwajcaria',
            'CI' => 'Côte d’Ivoire',
            'CK' => 'Wyspy Cooka',
            'CL' => 'Chile',
            'CM' => 'Kamerun',
            'CN' => 'Chiny',
            'CO' => 'Kolumbia',
            'CP' => 'Wyspa Clippertona',
            'CR' => 'Kostaryka',
            'CU' => 'Kuba',
            'CV' => 'Republika Zielonego Przylądka',
            'CW' => 'Curaçao',
            'CX' => 'Wyspa Bożego Narodzenia',
            'CY' => 'Cypr',
            'CZ' => 'Czechy',
            'DE' => 'Niemcy',
            'DG' => 'Diego Garcia',
            'DJ' => 'Dżibuti',
            'DK' => 'Dania',
            'DM' => 'Dominika',
            'DO' => 'Dominikana',
            'DZ' => 'Algieria',
            'EA' => 'Ceuta i Melilla',
            'EC' => 'Ekwador',
            'EE' => 'Estonia',
            'EG' => 'Egipt',
            'EH' => 'Sahara Zachodnia',
            'ER' => 'Erytrea',
            'ES' => 'Hiszpania',
            'ET' => 'Etiopia',
            'EU' => 'Unia Europejska',
            'EZ' => 'strefa euro',
            'FI' => 'Finlandia',
            'FJ' => 'Fidżi',
            'FK' => 'Falklandy',
            'FM' => 'Mikronezja',
            'FO' => 'Wyspy Owcze',
            'FR' => 'Francja',
            'GA' => 'Gabon',
            'GB' => 'Wielka Brytania',
            'GD' => 'Grenada',
            'GE' => 'Gruzja',
            'GF' => 'Gujana Francuska',
            'GG' => 'Guernsey',
            'GH' => 'Ghana',
            'GI' => 'Gibraltar',
            'GL' => 'Grenlandia',
            'GM' => 'Gambia',
            'GN' => 'Gwinea',
            'GP' => 'Gwadelupa',
            'GQ' => 'Gwinea Równikowa',
            'GR' => 'Grecja',
            'GS' => 'Georgia Południowa i Sandwich Południowy',
            'GT' => 'Gwatemala',
            'GU' => 'Guam',
            'GW' => 'Gwinea Bissau',
            'GY' => 'Gujana',
            'HK' => 'SRA Hongkong (Chiny)',
            'HM' => 'Wyspy Heard i McDonalda',
            'HN' => 'Honduras',
            'HR' => 'Chorwacja',
            'HT' => 'Haiti',
            'HU' => 'Węgry',
            'IC' => 'Wyspy Kanaryjskie',
            'ID' => 'Indonezja',
            'IE' => 'Irlandia',
            'IL' => 'Izrael',
            'IM' => 'Wyspa Man',
            'IN' => 'Indie',
            'IO' => 'Brytyjskie Terytorium Oceanu Indyjskiego',
            'IQ' => 'Irak',
            'IR' => 'Iran',
            'IS' => 'Islandia',
            'IT' => 'Włochy',
            'JE' => 'Jersey',
            'JM' => 'Jamajka',
            'JO' => 'Jordania',
            'JP' => 'Japonia',
            'KE' => 'Kenia',
            'KG' => 'Kirgistan',
            'KH' => 'Kambodża',
            'KI' => 'Kiribati',
            'KM' => 'Komory',
            'KN' => 'Saint Kitts i Nevis',
            'KP' => 'Korea Północna',
            'KR' => 'Korea Południowa',
            'KW' => 'Kuwejt',
            'KY' => 'Kajmany',
            'KZ' => 'Kazachstan',
            'LA' => 'Laos',
            'LB' => 'Liban',
            'LC' => 'Saint Lucia',
            'LI' => 'Liechtenstein',
            'LK' => 'Sri Lanka',
            'LR' => 'Liberia',
            'LS' => 'Lesotho',
            'LT' => 'Litwa',
            'LU' => 'Luksemburg',
            'LV' => 'Łotwa',
            'LY' => 'Libia',
            'MA' => 'Maroko',
            'MC' => 'Monako',
            'MD' => 'Mołdawia',
            'ME' => 'Czarnogóra',
            'MF' => 'Saint-Martin',
            'MG' => 'Madagaskar',
            'MH' => 'Wyspy Marshalla',
            'MK' => 'Macedonia Północna',
            'ML' => 'Mali',
            'MM' => 'Mjanma (Birma)',
            'MN' => 'Mongolia',
            'MO' => 'SRA Makau (Chiny)',
            'MP' => 'Mariany Północne',
            'MQ' => 'Martynika',
            'MR' => 'Mauretania',
            'MS' => 'Montserrat',
            'MT' => 'Malta',
            'MU' => 'Mauritius',
            'MV' => 'Malediwy',
            'MW' => 'Malawi',
            'MX' => 'Meksyk',
            'MY' => 'Malezja',
            'MZ' => 'Mozambik',
            'NA' => 'Namibia',
            'NC' => 'Nowa Kaledonia',
            'NE' => 'Niger',
            'NF' => 'Norfolk',
            'NG' => 'Nigeria',
            'NI' => 'Nikaragua',
            'NL' => 'Holandia',
            'NO' => 'Norwegia',
            'NP' => 'Nepal',
            'NR' => 'Nauru',
            'NU' => 'Niue',
            'NZ' => 'Nowa Zelandia',
            'OM' => 'Oman',
            'PA' => 'Panama',
            'PE' => 'Peru',
            'PF' => 'Polinezja Francuska',
            'PG' => 'Papua-Nowa Gwinea',
            'PH' => 'Filipiny',
            'PK' => 'Pakistan',
            'PL' => 'Polska',
            'PM' => 'Saint-Pierre i Miquelon',
            'PN' => 'Pitcairn',
            'PR' => 'Portoryko',
            'PS' => 'Terytoria Palestyńskie',
            'PT' => 'Portugalia',
            'PW' => 'Palau',
            'PY' => 'Paragwaj',
            'QA' => 'Katar',
            'QO' => 'Oceania — wyspy dalekie',
            'RE' => 'Reunion',
            'RO' => 'Rumunia',
            'RS' => 'Serbia',
            'RU' => 'Rosja',
            'RW' => 'Rwanda',
            'SA' => 'Arabia Saudyjska',
            'SB' => 'Wyspy Salomona',
            'SC' => 'Seszele',
            'SD' => 'Sudan',
            'SE' => 'Szwecja',
            'SG' => 'Singapur',
            'SH' => 'Wyspa Świętej Heleny',
            'SI' => 'Słowenia',
            'SJ' => 'Svalbard i Jan Mayen',
            'SK' => 'Słowacja',
            'SL' => 'Sierra Leone',
            'SM' => 'San Marino',
            'SN' => 'Senegal',
            'SO' => 'Somalia',
            'SR' => 'Surinam',
            'SS' => 'Sudan Południowy',
            'ST' => 'Wyspy Świętego Tomasza i Książęca',
            'SV' => 'Salwador',
            'SX' => 'Sint Maarten',
            'SY' => 'Syria',
            'SZ' => 'Eswatini',
            'TA' => 'Tristan da Cunha',
            'TC' => 'Turks i Caicos',
            'TD' => 'Czad',
            'TF' => 'Francuskie Terytoria Południowe i Antarktyczne',
            'TG' => 'Togo',
            'TH' => 'Tajlandia',
            'TJ' => 'Tadżykistan',
            'TK' => 'Tokelau',
            'TL' => 'Timor Wschodni',
            'TM' => 'Turkmenistan',
            'TN' => 'Tunezja',
            'TO' => 'Tonga',
            'TR' => 'Turcja',
            'TT' => 'Trynidad i Tobago',
            'TV' => 'Tuvalu',
            'TW' => 'Tajwan',
            'TZ' => 'Tanzania',
            'UA' => 'Ukraina',
            'UG' => 'Uganda',
            'UM' => 'Dalekie Wyspy Mniejsze Stanów Zjednoczonych',
            'UN' => 'Organizacja Narodów Zjednoczonych',
            'US' => 'Stany Zjednoczone',
            'UY' => 'Urugwaj',
            'UZ' => 'Uzbekistan',
            'VA' => 'Watykan',
            'VC' => 'Saint Vincent i Grenadyny',
            'VE' => 'Wenezuela',
            'VG' => 'Brytyjskie Wyspy Dziewicze',
            'VI' => 'Wyspy Dziewicze Stanów Zjednoczonych',
            'VN' => 'Wietnam',
            'VU' => 'Vanuatu',
            'WF' => 'Wallis i Futuna',
            'WS' => 'Samoa',
            'XA' => 'Pseudoakcenty',
            'XB' => 'Pseudodwukierunkowe',
            'XK' => 'Kosowo',
            'YE' => 'Jemen',
            'YT' => 'Majotta',
            'ZA' => 'Republika Południowej Afryki',
            'ZM' => 'Zambia',
            'ZW' => 'Zimbabwe',
            'ZZ' => 'Nieznany region',
        ];

        $needle=mb_strtolower($q,'UTF-8');
        $rows=$this->pdo->query("SELECT id,name,iso2,iso3 FROM ml_countries ORDER BY name")->fetchAll();
        $items=[];

        foreach($rows as $row){
            $iso2=mb_strtoupper((string)($row['iso2']??''),'UTF-8');
            $english=(string)($row['name']??'');
            $iso3=(string)($row['iso3']??'');
            $polish=$polishNames[$iso2]??'';

            $haystacks=[$english,$iso2,$iso3,$polish];
            $matched=false;
            foreach($haystacks as $value){
                if($value!=='' && mb_stripos($value,$q,0,'UTF-8')!==false){ $matched=true; break; }
            }
            if(!$matched)continue;

            $label=$polish!=='' && mb_strtolower($polish,'UTF-8')!==mb_strtolower($english,'UTF-8')
                ? $polish.' · '.$english.' · '.$iso2
                : $english.' · '.$iso2;

            $items[]=['id'=>(int)$row['id'],'label'=>$label];
            if(count($items)>=30)break;
        }

        return$this->json($response,['status'=>'ok','items'=>$items]);
    }

    public function options(Request $request, Response $response, array $args): Response
    {
        $adminId=$this->requireAdmin($response);if($adminId instanceof Response)return$adminId;
        $type=(string)($args['type']??'');$field=(string)($args['field']??'');
        $config=$this->config($type);if(!$config)return$this->error($response,'Nieznany typ słownika.',404);
        $allowed=['manufacturer','family'];
        if($type!=='aircraft'||!in_array($field,$allowed,true))return$this->error($response,'Nieobsługiwane pole opcji.',404);
        $columns=$this->columns($config['table']);if(!isset($columns[$field]))return$this->json($response,['status'=>'ok','items'=>[]]);
        $sql="SELECT DISTINCT {$field} value FROM {$config['table']} WHERE {$field} IS NOT NULL AND TRIM({$field})<>'' ORDER BY {$field}";
        $rows=$this->pdo->query($sql)->fetchAll();
        $items=[];foreach($rows as $i=>$r)$items[]=['id'=>$i+1,'label'=>(string)$r['value']];
        return$this->json($response,['status'=>'ok','items'=>$items]);
    }

    public function aliases(Request $request, Response $response): Response
    {
        $adminId=$this->requireAdmin($response);if($adminId instanceof Response)return$adminId;
        $q=$request->getQueryParams();$type=(string)($q['type']??'all');$source=(string)($q['source']??'all');$search=trim((string)($q['q']??''));$where=[];$params=[];
        if(in_array($type,['airport','airline','aircraft_type'],true)){$where[]='a.entity_type=:type';$params['type']=$type;}
        if(in_array($source,['manual','migration','import','suggestion','merge'],true)){$where[]='a.source=:source';$params['source']=$source;}
        if($search!==''){$where[]='(a.alias LIKE :q_alias OR CAST(a.entity_id AS CHAR)=:q_id)';$params['q_alias']='%'.$search.'%';$params['q_id']=$search;}
        $whereSql=$where?'WHERE '.implode(' AND ',$where):'';
        $stmt=$this->pdo->prepare("SELECT a.*,u.nick created_by_nick,uu.nick updated_by_nick FROM ml_entity_aliases a LEFT JOIN ml_users u ON u.id=a.created_by LEFT JOIN ml_users uu ON uu.id=a.updated_by {$whereSql} ORDER BY a.created_at DESC,a.id DESC LIMIT 500");$this->bind($stmt,$params);$stmt->execute();$rows=$stmt->fetchAll();foreach($rows as &$r)$r['target_label']=$this->targetLabel((string)$r['entity_type'],(int)$r['entity_id']);unset($r);return$this->json($response,['status'=>'ok','aliases'=>$rows]);
    }

    public function createAlias(Request $request, Response $response): Response
    {
        $adminId=$this->requireAdmin($response);if($adminId instanceof Response)return$adminId;$d=(array)($request->getParsedBody()?:[]);$type=(string)($d['entity_type']??'');$id=(int)($d['entity_id']??0);$alias=trim((string)($d['alias']??''));$source=(string)($d['source']??'manual');if(!in_array($source,['manual','migration','import','suggestion','merge'],true))$source='manual';if(!in_array($type,['airport','airline','aircraft_type'],true)||$id<=0||mb_strlen($alias,'UTF-8')<1)return$this->error($response,'Niepoprawne dane aliasu.',422);if(!$this->targetExists($type,$id))return$this->error($response,'Docelowy rekord nie istnieje.',422);$normalized=$this->normalizeAlias($alias);
        try{$stmt=$this->pdo->prepare('INSERT INTO ml_entity_aliases(entity_type,entity_id,alias,alias_normalized,source,usage_count,created_by,updated_by) VALUES(:type,:id,:alias,:normalized,:source,0,:admin,:updated_by)');$stmt->execute(['type'=>$type,'id'=>$id,'alias'=>$alias,'normalized'=>$normalized,'source'=>$source,'admin'=>$adminId,'updated_by'=>$adminId]);}catch(\PDOException $e){if((string)$e->getCode()==='23000')return$this->error($response,'Taki alias jest już zdefiniowany. Każda znormalizowana nazwa może wskazywać tylko jeden rekord danego typu.',409);throw$e;}
        $newId=(int)$this->pdo->lastInsertId();$this->audit->log($adminId,'alias.create','alias',$newId,'Dodano alias: '.$alias,null,['entity_type'=>$type,'entity_id'=>$id,'alias'=>$alias,'source'=>$source],$this->ip($request));return$this->json($response,['status'=>'ok','message'=>'Alias został dodany.','id'=>$newId]);
    }

    public function updateAlias(Request $request, Response $response, array $args): Response
    {
        $adminId=$this->requireAdmin($response);if($adminId instanceof Response)return$adminId;$id=(int)($args['id']??0);$s=$this->pdo->prepare('SELECT * FROM ml_entity_aliases WHERE id=:id');$s->execute(['id'=>$id]);$before=$s->fetch();if(!$before)return$this->error($response,'Nie znaleziono aliasu.',404);
        $d=(array)($request->getParsedBody()?:[]);$type=(string)($d['entity_type']??$before['entity_type']);$entityId=(int)($d['entity_id']??$before['entity_id']);$alias=trim((string)($d['alias']??$before['alias']));$source=(string)($d['source']??$before['source']);if(!in_array($type,['airport','airline','aircraft_type'],true)||$entityId<=0||$alias==='')return$this->error($response,'Niepoprawne dane aliasu.',422);if(!in_array($source,['manual','migration','import','suggestion','merge'],true))return$this->error($response,'Niepoprawne źródło aliasu.',422);if(!$this->targetExists($type,$entityId))return$this->error($response,'Docelowy rekord nie istnieje.',422);$normalized=$this->normalizeAlias($alias);
        try{$u=$this->pdo->prepare('UPDATE ml_entity_aliases SET entity_type=:type,entity_id=:entity_id,alias=:alias,alias_normalized=:normalized,source=:source,updated_by=:admin,updated_at=NOW() WHERE id=:id');$u->execute(['type'=>$type,'entity_id'=>$entityId,'alias'=>$alias,'normalized'=>$normalized,'source'=>$source,'admin'=>$adminId,'id'=>$id]);}catch(\PDOException $e){if((string)$e->getCode()==='23000')return$this->error($response,'Taki alias koliduje z istniejącym aliasem.',409);throw$e;}
        $s->execute(['id'=>$id]);$after=$s->fetch();$this->audit->log($adminId,'alias.update','alias',$id,'Zmieniono alias: '.$alias,$before?:null,$after?:null,$this->ip($request));return$this->json($response,['status'=>'ok','message'=>'Alias został zapisany.','alias'=>$after]);
    }

    public function deleteAlias(Request $request, Response $response, array $args): Response
    {
        $adminId=$this->requireAdmin($response);if($adminId instanceof Response)return$adminId;$id=(int)($args['id']??0);$s=$this->pdo->prepare('SELECT * FROM ml_entity_aliases WHERE id=:id');$s->execute(['id'=>$id]);$before=$s->fetch();if(!$before)return$this->error($response,'Nie znaleziono aliasu.',404);$this->pdo->prepare('DELETE FROM ml_entity_aliases WHERE id=:id')->execute(['id'=>$id]);$this->audit->log($adminId,'alias.delete','alias',$id,'Usunięto alias: '.$before['alias'],$before,null,$this->ip($request));return$this->json($response,['status'=>'ok','message'=>'Alias został usunięty.']);
    }

    public function duplicates(Request $request, Response $response): Response
    {
        $adminId=$this->requireAdmin($response);if($adminId instanceof Response)return$adminId;$type=(string)($request->getQueryParams()['type']??'airports');$groups=[];
        if($type==='airports'){$groups=array_merge($this->duplicateGroups('ml_airports','iata_code','IATA'),$this->duplicateGroups('ml_airports','icao_code','ICAO'),$this->duplicateNameCity());}
        elseif($type==='airlines'){$groups=array_merge($this->duplicateGroups('ml_airlines','iata_code','IATA'),$this->duplicateGroups('ml_airlines','icao_code','ICAO'),$this->duplicateGroups('ml_airlines','name','Nazwa'));}
        elseif($type==='aircraft'){$groups=array_merge($this->duplicateGroups('ml_aircraft_types','name','Nazwa'),$this->duplicateAircraftSignature());}
        else return$this->error($response,'Nieznany typ duplikatów.',422);
        usort($groups,fn($a,$b)=>($b['count']<=>$a['count'])?:strcmp($a['key'],$b['key']));return$this->json($response,['status'=>'ok','type'=>$type,'groups'=>array_slice($groups,0,150)]);
    }

    private function duplicateGroups(string $table,string $column,string $reason):array{$cols=$this->columns($table);if(!isset($cols[$column]))return[];$sql="SELECT LOWER(TRIM({$column})) dup_key,COUNT(*) cnt,GROUP_CONCAT(id ORDER BY id SEPARATOR ',') ids FROM {$table} WHERE {$column} IS NOT NULL AND TRIM({$column})<>'' GROUP BY LOWER(TRIM({$column})) HAVING COUNT(*)>1 ORDER BY cnt DESC LIMIT 75";$rows=$this->pdo->query($sql)->fetchAll();return array_map(fn($r)=>['reason'=>$reason,'key'=>$r['dup_key'],'count'=>(int)$r['cnt'],'ids'=>array_map('intval',explode(',',$r['ids']))],$rows);}
    private function duplicateNameCity():array{$r=$this->pdo->query("SELECT CONCAT(LOWER(TRIM(name)),'|',LOWER(TRIM(COALESCE(city,'')))) dup_key,COUNT(*) cnt,GROUP_CONCAT(id ORDER BY id SEPARATOR ',') ids FROM ml_airports WHERE name IS NOT NULL AND TRIM(name)<>'' GROUP BY dup_key HAVING COUNT(*)>1 ORDER BY cnt DESC LIMIT 75")->fetchAll();return array_map(fn($x)=>['reason'=>'Nazwa + miasto','key'=>$x['dup_key'],'count'=>(int)$x['cnt'],'ids'=>array_map('intval',explode(',',$x['ids']))],$r);}
    private function duplicateAircraftSignature():array{$r=$this->pdo->query("SELECT CONCAT(LOWER(TRIM(COALESCE(manufacturer,''))),'|',LOWER(TRIM(COALESCE(model,''))),'|',LOWER(TRIM(COALESCE(variant,'')))) dup_key,COUNT(*) cnt,GROUP_CONCAT(id ORDER BY id SEPARATOR ',') ids FROM ml_aircraft_types GROUP BY dup_key HAVING COUNT(*)>1 AND dup_key<>'||' ORDER BY cnt DESC LIMIT 75")->fetchAll();return array_map(fn($x)=>['reason'=>'Producent + model + wariant','key'=>$x['dup_key'],'count'=>(int)$x['cnt'],'ids'=>array_map('intval',explode(',',$x['ids']))],$r);}
    private function columns(string $table):array{$s=$this->pdo->prepare("SELECT COLUMN_NAME,DATA_TYPE,IS_NULLABLE,COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table ORDER BY ORDINAL_POSITION");$s->execute(['table'=>$table]);$out=[];foreach($s->fetchAll() as $r)$out[$r['COLUMN_NAME']]=$r;return$out;}
    private function config(string $type):?array{return self::CONFIG[$type]??null;}
    private function find(string $table,int $id):array|false{$s=$this->pdo->prepare("SELECT * FROM {$table} WHERE id=:id LIMIT 1");$s->execute(['id'=>$id]);return$s->fetch();}
    private function normalize(string $c,mixed $v):mixed{
        if(in_array($c,['country_id'],true))return($v===null||$v==='')?null:(int)$v;
        if(in_array($c,['latitude','longitude'],true))return($v===null||$v==='')?null:(float)$v;
        if($c==='is_active')return(bool)$v?1:0;
        if($v===null)return null;
        $v=trim((string)$v);
        if($v==='')return null;
        if(in_array($c,['iata_code','icao_code'],true))$v=strtoupper($v);
        return$v;
    }
    private function validateCodes(Response $response,string $table,int $id,array $v):void{
        foreach(['iata_code','icao_code'] as $c){
            if(!array_key_exists($c,$v)||$v[$c]===null)continue;
            $code=strtoupper(trim((string)$v[$c]));
            $length=$table==='ml_airports'?($c==='iata_code'?3:4):($c==='iata_code'?2:3);
            if(!preg_match('/^[A-Z0-9]{'.$length.'}$/',$code))throw new \RuntimeException(strtoupper(str_replace('_code','',$c)).' ma niepoprawny format (wymagane '.$length.' znaki).');
            $s=$this->pdo->prepare("SELECT id FROM {$table} WHERE UPPER({$c})=UPPER(:code) AND id<>:id LIMIT 1");
            $s->execute(['code'=>$code,'id'=>$id]);
            if($s->fetch())throw new \RuntimeException(strtoupper(str_replace('_code','',$c)).' jest już używany przez inny rekord.');
        }
    }
    private function targetExists(string $type,int $id):bool{$map=['airport'=>'ml_airports','airline'=>'ml_airlines','aircraft_type'=>'ml_aircraft_types'];$s=$this->pdo->prepare('SELECT 1 FROM '.$map[$type].' WHERE id=:id');$s->execute(['id'=>$id]);return(bool)$s->fetchColumn();}
    private function targetLabel(string $type,int $id):string{$map=['airport'=>['ml_airports',"CONCAT(COALESCE(iata_code,icao_code,'---'),' · ',name)"],'airline'=>['ml_airlines',"CONCAT(name,' · ',COALESCE(iata_code,''))"],'aircraft_type'=>['ml_aircraft_types','name']];[$t,$e]=$map[$type];$s=$this->pdo->prepare("SELECT {$e} FROM {$t} WHERE id=:id");$s->execute(['id'=>$id]);return(string)($s->fetchColumn()?:('#'.$id));}
    private function normalizeAlias(string $v):string{$v=mb_strtolower(trim($v),'UTF-8');$v=preg_replace('/\s+/u',' ',$v)??$v;return$v;}
    private function perPage(mixed $v):int{$n=(int)$v;return in_array($n,[25,50,100],true)?$n:25;}
    private function bind(\PDOStatement $s,array $p):void{foreach($p as $k=>$v)$s->bindValue(':'.$k,$v);}
    private function ip(Request $r):?string{$s=$r->getServerParams();return isset($s['REMOTE_ADDR'])?(string)$s['REMOTE_ADDR']:null;}
    private function requireAdmin(Response $response):int|Response{try{$id=$this->auth->requireUserId();}catch(\RuntimeException){return$this->error($response,'Musisz się zalogować.',401);}$s=$this->pdo->prepare('SELECT is_admin,is_active FROM ml_users WHERE id=:id');$s->execute(['id'=>$id]);$u=$s->fetch();if(!$u||!(bool)$u['is_admin']||!(bool)$u['is_active'])return$this->error($response,'Brak uprawnień administratora.',403);return$id;}
    private function json(Response $r,array $d,int $s=200):Response{$r->getBody()->write(json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));return$r->withStatus($s)->withHeader('Content-Type','application/json; charset=utf-8');}
    private function error(Response $r,string $m,int $s):Response{return$this->json($r,['status'=>'error','message'=>$m],$s);}
}
