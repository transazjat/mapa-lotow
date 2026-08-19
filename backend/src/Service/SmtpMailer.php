<?php

declare(strict_types=1);

namespace Transazja\MapaLotowApi\Service;

final class SmtpMailer
{
    public function __construct(
        private string $host,
        private int $port,
        private string $fromEmail,
        private string $fromName,
        private ?string $username = null,
        private ?string $password = null,
        private string $encryption = 'none'
    ) {
    }

    public function send(
        string $to,
        string $subject,
        string $html
    ): void {
        $transport = $this->encryption === 'ssl'
            ? 'ssl://' . $this->host
            : $this->host;

        $socket = @stream_socket_client(
            sprintf('%s:%d', $transport, $this->port),
            $errno,
            $errstr,
            10
        );

        if (!$socket) {
            throw new \RuntimeException(
                sprintf('Nie można połączyć z SMTP: %s (%d)', $errstr, $errno)
            );
        }

        stream_set_timeout($socket, 10);

        try {
            $this->expect($socket, [220]);
            $this->command($socket, 'EHLO mapalotow.local', [250]);

            if ($this->encryption === 'tls') {
                $this->command($socket, 'STARTTLS', [220]);

                if (!stream_socket_enable_crypto(
                    $socket,
                    true,
                    STREAM_CRYPTO_METHOD_TLS_CLIENT
                )) {
                    throw new \RuntimeException('Nie udało się uruchomić STARTTLS.');
                }

                $this->command($socket, 'EHLO mapalotow.local', [250]);
            }

            if ($this->username !== null && $this->username !== '') {
                $this->command($socket, 'AUTH LOGIN', [334]);
                $this->command($socket, base64_encode($this->username), [334]);
                $this->command($socket, base64_encode($this->password ?? ''), [235]);
            }

            $this->command(
                $socket,
                'MAIL FROM:<' . $this->fromEmail . '>',
                [250]
            );

            $this->command(
                $socket,
                'RCPT TO:<' . $to . '>',
                [250, 251]
            );

            $this->command($socket, 'DATA', [354]);

            $headers = [
                'From: ' . $this->encodeHeader($this->fromName)
                    . ' <' . $this->fromEmail . '>',
                'To: <' . $to . '>',
                'Subject: ' . $this->encodeHeader($subject),
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
                'Date: ' . date(DATE_RFC2822),
            ];

            $body = implode("\r\n", $headers)
                . "\r\n\r\n"
                . $this->dotStuff($html)
                . "\r\n.";

            fwrite($socket, $body . "\r\n");
            $this->expect($socket, [250]);

            $this->command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }

    private function command($socket, string $command, array $codes): string
    {
        fwrite($socket, $command . "\r\n");
        return $this->expect($socket, $codes);
    }

    private function expect($socket, array $codes): string
    {
        $response = '';

        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;

            if (strlen($line) >= 4 && $line[3] === ' ') {
                $code = (int) substr($line, 0, 3);

                if (!in_array($code, $codes, true)) {
                    throw new \RuntimeException(
                        'Błąd SMTP: ' . trim($response)
                    );
                }

                return $response;
            }
        }

        throw new \RuntimeException('SMTP zakończył połączenie bez odpowiedzi.');
    }

    private function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function dotStuff(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace('/^\./m', '..', $value) ?? $value;
        return str_replace("\n", "\r\n", $value);
    }
}
