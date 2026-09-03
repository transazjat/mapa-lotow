<?php

declare(strict_types=1);

namespace Transazja\MapaLotowApi\Controller;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Transazja\MapaLotowApi\Security\AuthService;

final class AdminAuditController
{
    public function __construct(private PDO $pdo, private AuthService $auth) {}

    public function index(Request $request, Response $response): Response
    {
        $admin=$this->requireAdmin($response);if($admin instanceof Response)return$admin;$q=$request->getQueryParams();$search=trim((string)($q['q']??''));$entity=trim((string)($q['entity_type']??''));$sort=(string)($q['sort']??'date_desc');$page=max(1,(int)($q['page']??1));$perPage=in_array((int)($q['per_page']??50),[25,50,100],true)?(int)$q['per_page']:50;$where=[];$params=[];
        if($search!==''){
            $like='%'.$search.'%';
            $where[]='(l.summary LIKE :q_summary OR l.action LIKE :q_action OR u.nick LIKE :q_admin OR CAST(l.entity_id AS CHAR)=:exact)';
            $params['q_summary']=$like;$params['q_action']=$like;$params['q_admin']=$like;$params['exact']=$search;
        }
        if($entity!==''){$where[]='l.entity_type=:entity';$params['entity']=$entity;}$ws=$where?'WHERE '.implode(' AND ',$where):'';$count=$this->pdo->prepare("SELECT COUNT(*) FROM ml_admin_audit_log l LEFT JOIN ml_users u ON u.id=l.admin_user_id {$ws}");foreach($params as$k=>$v)$count->bindValue(':'.$k,$v);$count->execute();$total=(int)$count->fetchColumn();$offset=($page-1)*$perPage;
        $orders=['date_asc'=>'l.created_at ASC,l.id ASC','date_desc'=>'l.created_at DESC,l.id DESC','admin_asc'=>'u.nick ASC,l.created_at DESC','admin_desc'=>'u.nick DESC,l.created_at DESC','action_asc'=>'l.action ASC,l.created_at DESC','action_desc'=>'l.action DESC,l.created_at DESC','entity_asc'=>'l.entity_type ASC,l.entity_id ASC','entity_desc'=>'l.entity_type DESC,l.entity_id DESC'];$order=$orders[$sort]??$orders['date_desc'];
        $stmt=$this->pdo->prepare("SELECT l.*,u.nick admin_nick FROM ml_admin_audit_log l LEFT JOIN ml_users u ON u.id=l.admin_user_id {$ws} ORDER BY {$order} LIMIT :limit OFFSET :offset");foreach($params as$k=>$v)$stmt->bindValue(':'.$k,$v);$stmt->bindValue(':limit',$perPage,PDO::PARAM_INT);$stmt->bindValue(':offset',$offset,PDO::PARAM_INT);$stmt->execute();
        return$this->json($response,['status'=>'ok','page'=>$page,'per_page'=>$perPage,'total'=>$total,'pages'=>max(1,(int)ceil($total/$perPage)),'items'=>$stmt->fetchAll()]);
    }
    private function requireAdmin(Response $r):int|Response{try{$id=$this->auth->requireUserId();}catch(\RuntimeException){return$this->json($r,['status'=>'error','message'=>'Musisz się zalogować.'],401);}$s=$this->pdo->prepare('SELECT is_admin,is_active FROM ml_users WHERE id=:id');$s->execute(['id'=>$id]);$u=$s->fetch();if(!$u||!(bool)$u['is_admin']||!(bool)$u['is_active'])return$this->json($r,['status'=>'error','message'=>'Brak uprawnień administratora.'],403);return$id;}
    private function json(Response $r,array$d,int$s=200):Response{$r->getBody()->write(json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));return$r->withStatus($s)->withHeader('Content-Type','application/json; charset=utf-8');}
}
