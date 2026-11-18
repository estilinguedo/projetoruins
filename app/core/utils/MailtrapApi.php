<?php
declare(strict_types=1);
namespace app\core\utils;

/** Envio HTTPS sem depender de cURL ou allow_url_fopen. */
final class MailtrapApi {
    public static function send(string $to, string $subject, string $body): void {
        $token = trim((string)Config::get('mail.api_token', ''));
        $inbox = (string)Config::get('mail.inbox_id', '');
        if ($token === '' || preg_match('/[\r\n]/', $token) || !ctype_digit($inbox) || (int)$inbox < 1) {
            throw new \RuntimeException('Mailtrap API: configure api_token e inbox_id em app/etc/config.json.');
        }
        $payload = json_encode([
            'from' => ['email' => (string)Config::get('mail.from_email'), 'name' => (string)Config::get('mail.from_name')],
            'to' => [['email' => $to]], 'subject' => $subject, 'text' => $body
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $context = stream_context_create(['ssl' => [
            'verify_peer' => true, 'verify_peer_name' => true,
            'peer_name' => 'sandbox.api.mailtrap.io'
        ]]);
        $socket = @stream_socket_client('ssl://sandbox.api.mailtrap.io:443', $errno, $error, 15, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) throw new \RuntimeException('Mailtrap API: falha na conexao HTTPS (codigo '.(int)$errno.').');
        try {
            stream_set_timeout($socket, 20);
            $request = "POST /api/send/$inbox HTTP/1.0\r\nHost: sandbox.api.mailtrap.io\r\n".
                "Authorization: Bearer $token\r\nContent-Type: application/json\r\nAccept: application/json\r\n".
                'Content-Length: '.strlen($payload)."\r\nConnection: close\r\n\r\n".$payload;
            $offset = 0;
            while ($offset < strlen($request)) {
                $written = @fwrite($socket, substr($request, $offset));
                if (!$written) throw new \RuntimeException('Mailtrap API: conexao interrompida durante o envio.');
                $offset += $written;
            }
            $response = '';
            while (!feof($socket)) {
                $chunk = @fread($socket, 8192);
                if ($chunk === false || stream_get_meta_data($socket)['timed_out']) {
                    throw new \RuntimeException('Mailtrap API: falha ou tempo esgotado aguardando resposta.');
                }
                $response .= $chunk;
                if (strlen($response) > 1048576) throw new \RuntimeException('Mailtrap API: resposta excedeu o limite.');
            }
        } finally { fclose($socket); }
        self::validateResponse($response);
    }

    // Nunca registra token, corpo do email ou resposta bruta do provedor.
    public static function validateResponse(string $response): void {
        $parts = explode("\r\n\r\n", $response, 2);
        if (count($parts) !== 2 || !preg_match('~^HTTP/1\.[01] ([0-9]{3})\b~', $parts[0], $match)) {
            throw new \RuntimeException('Mailtrap API: resposta HTTP invalida.');
        }
        [$headers, $body] = $parts;
        $status = (int)$match[1];
        if ($status < 200 || $status >= 300) {
            $reason = match ($status) {
                401 => 'token invalido ou expirado',
                403 => 'token sem permissao para esta sandbox',
                404 => 'sandbox nao encontrada; confira inbox_id e permissoes',
                429 => 'limite de envios atingido; aguarde pelo menos 10 segundos e confira a cota',
                400, 422 => 'dados do email rejeitados; confira remetente e destinatario',
                default => 'envio nao confirmado pelo provedor'
            };
            throw new \RuntimeException("Mailtrap API HTTP $status: $reason.");
        }
        if (preg_match('/^Transfer-Encoding:\s*chunked\s*$/mi', $headers)) {
            $decoded = '';
            while (true) {
                $end = strpos($body, "\r\n");
                if ($end === false || !preg_match('/^[0-9a-f]+(?:;.*)?$/i', substr($body, 0, $end))) {
                    throw new \RuntimeException('Mailtrap API: resposta fragmentada invalida.');
                }
                $length = hexdec(explode(';', substr($body, 0, $end))[0]);
                $body = substr($body, $end + 2);
                if ($length === 0) break;
                if ($length > strlen($body) - 2 || substr($body, (int)$length, 2) !== "\r\n") {
                    throw new \RuntimeException('Mailtrap API: resposta incompleta.');
                }
                $decoded .= substr($body, 0, (int)$length);
                $body = substr($body, (int)$length + 2);
            }
            $body = $decoded;
        }
        $result = json_decode($body, true);
        if (!is_array($result) || ($result['success'] ?? false) !== true) {
            throw new \RuntimeException('Mailtrap API: provedor nao confirmou o envio.');
        }
    }
}
