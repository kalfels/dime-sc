<?php

namespace App\Services;

use App\Core\Env;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

class EmailService
{
    private $mail;
    private $smtpHost;
    private $smtpPort;
    private $smtpUsername;
    private $smtpPassword;
    private $smtpFromEmail;
    private $smtpFromName;

    public function __construct($debug = false)
    {
        $this->smtpHost = Env::get('SMTP_HOST', 'smtp.zoho.com');
        $this->smtpPort = (int) Env::get('SMTP_PORT', 587);
        $this->smtpUsername = Env::get('SMTP_USERNAME', '');
        $this->smtpPassword = Env::get('SMTP_PASSWORD', '');
        $this->smtpFromEmail = Env::get('SMTP_FROM_EMAIL', $this->smtpUsername);
        $this->smtpFromName = Env::get('SMTP_FROM_NAME', 'Sistema DIME-SC');

        $this->mail = new PHPMailer(true);
        $this->configureMailer($debug);
    }

    private function configureMailer($debug = false)
    {
        try {
            // Configurações do servidor
            $this->mail->isSMTP();
            $this->mail->Host = $this->smtpHost;
            
            // Configurações de autenticação para Zoho
            $this->mail->SMTPAuth = true;
            $this->mail->Username = $this->smtpUsername;
            $this->mail->Password = $this->smtpPassword;
            
            // Configurações de segurança
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Usa STARTTLS
            $this->mail->Port = $this->smtpPort;
            $this->mail->CharSet = 'UTF-8';
            
            // Configurações de depuração (apenas se solicitado explicitamente)
            if ($debug) {
                $this->mail->SMTPDebug = SMTP::DEBUG_SERVER;
                $this->mail->Debugoutput = function($str, $level) {
                    error_log("PHPMailer (Nível $level): $str");
                    if (php_sapi_name() !== 'cli') {
                        echo "[DEBUG] $str<br>"; // HTML no navegador
                    } else {
                        echo "[DEBUG] $str\n"; // Texto no terminal
                    }
                };
            }
            
            // Configurações de timeout
            $this->mail->Timeout = 30; // 30 segundos de timeout
            
            // Remetente
            $this->mail->setFrom($this->smtpFromEmail, $this->smtpFromName);
            // Define o endereço para resposta como o e-mail institucional
            $this->mail->addReplyTo('no-reply@dime-sc.com.br', 'Suporte DIME-SC');
            
            // Configurações de segurança
            $this->mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                    'verify_depth' => 3,
                    'peer_name' => 'smtp.zoho.com',
                    'cafile' => '/etc/ssl/certs/ca-certificates.crt',
                ]
            ];
            
            // Configurações adicionais
            $this->mail->XMailer = ' '; // Remove o cabeçalho X-Mailer
            $this->mail->SMTPAutoTLS = true; // Habilita TLS automático
            $this->mail->SMTPKeepAlive = false; // Fecha a conexão após o envio

        } catch (Exception $e) {
            error_log("Erro ao configurar o PHPMailer: " . $e->getMessage());
            throw new \Exception("Erro ao configurar o serviço de e-mail.");
        }
    }

    /**
     * Envia um e-mail de redefinição de senha
     * 
     * @param string $toEmail E-mail do destinatário
     * @param string $nome Nome do destinatário
     * @param string $token Token de redefinição de senha
     * @return bool Retorna true se o e-mail for enviado com sucesso
     */
    public function enviarEmailRedefinicaoSenha($toEmail, $nome, $token)
    {
        try {
            // Determinar a URL base dinamicamente
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $baseUrl = $protocol . '://' . $host;
            
            $resetLink = $baseUrl . "/recuperar-senha.php?token=" . urlencode($token);
            
            $assunto = "Redefinição de Senha - DIME-SC";
            
            $mensagem = "
                <h2>Redefinição de Senha</h2>
                <p>Olá, {$nome}!</p>
                <p>Recebemos uma solicitação para redefinir a senha da sua conta no Sistema DIME-SC.</p>
                <p>Clique no link abaixo para criar uma nova senha:</p>
                <p><a href='{$resetLink}' style='background-color: #4e73df; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px;'>Redefinir Senha</a></p>
                <p>Se você não solicitou a redefinição de senha, por favor, desconsidere este e-mail.</p>
                <p>Este link expirará em 1 hora.</p>
                <p>Atenciosamente,<br>Equipe DIME-SC</p>
            ";

            return $this->enviarEmail($toEmail, $nome, $assunto, $mensagem);
            
        } catch (Exception $e) {
            error_log("Erro ao enviar e-mail de redefinição: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envia um e-mail de boas-vindas para novos usuários
     * 
     * @param string $toEmail E-mail do destinatário
     * @param string $nome Nome do destinatário
     * @param string $email E-mail de login
     * @param string $senha Senha temporária
     * @return bool Retorna true se o e-mail for enviado com sucesso
     */
    public function enviarEmailBoasVindas($toEmail, $nome, $email, $senha)
    {
        try {
            // Determinar a URL base dinamicamente
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $baseUrl = $protocol . '://' . $host;
            
            $assunto = "Bem-vindo ao Sistema DIME-SC";
            
            $mensagem = "
                <h2>Bem-vindo ao Sistema DIME-SC!</h2>
                <p>Olá, {$nome}!</p>
                <p>Sua conta foi criada com sucesso no Sistema DIME-SC.</p>
                <p><strong>Seus dados de acesso são:</strong></p>
                <p>E-mail: {$email}<br>
                Senha temporária: {$senha}</p>
                <p>Recomendamos que você altere sua senha no primeiro acesso.</p>
                <p>Para acessar o sistema, clique no link abaixo:</p>
                <p><a href='{$baseUrl}' style='background-color: #4e73df; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px;'>Acessar o Sistema</a></p>
                <p>Atenciosamente,<br>Equipe DIME-SC</p>
            ";

            return $this->enviarEmail($toEmail, $nome, $assunto, $mensagem);
            
        } catch (Exception $e) {
            error_log("Erro ao enviar e-mail de boas-vindas: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Método genérico para envio de e-mails
     * 
     * @param string $toEmail E-mail do destinatário
     * @param string $toName Nome do destinatário
     * @param string $assunto Assunto do e-mail
     * @param string $mensagem Corpo do e-mail (HTML)
     * @return bool Retorna true se o e-mail for enviado com sucesso
     */
    private function enviarEmail($toEmail, $toName, $assunto, $mensagem)
    {
        try {
            // Limpa destinatários anteriores
            $this->mail->clearAddresses();
            
            // Destinatário
            $this->mail->addAddress($toEmail, $toName);
            
            // Conteúdo
            $this->mail->isHTML(true);
            $this->mail->Subject = $assunto;
            
            // Layout do e-mail
            $emailTemplate = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <title>{$assunto}</title>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background-color: #4e73df; padding: 20px; text-align: center; color: white; }
                        .content { padding: 20px; background-color: #f9f9f9; }
                        .footer { padding: 15px; text-align: center; font-size: 12px; color: #777; }
                        .button { background-color: #4e73df; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 10px 0; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>DIME-SC</h1>
                        </div>
                        <div class='content'>
                            {$mensagem}
                        </div>
                        <div class='footer'>
                            <p>Este é um e-mail automático, por favor não responda.</p>
                            <p>&copy; " . date('Y') . " DIME-SC. Todos os direitos reservados.</p>
                        </div>
                    </div>
                </body>
                </html>
            ";
            
            $this->mail->Body = $emailTemplate;
            
            // Envia o e-mail
            $this->mail->send();
            return true;
            
        } catch (Exception $e) {
            error_log("Erro ao enviar e-mail para {$toEmail}: " . $this->mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Método para teste de envio de e-mail
     * 
     * @param string $email E-mail do destinatário
     * @param string $assunto Assunto do e-mail
     * @param string $mensagem Conteúdo da mensagem
     * @return bool Retorna true se o e-mail for enviado com sucesso, false caso contrário
     */
    public function enviarEmailTeste($email, $assunto, $mensagem, $debug = false)
    {
        try {
            // Reconfigurar mailer se o modo debug mudou
            if ($debug && !$this->mail->SMTPDebug) {
                $this->configureMailer(true);
            } elseif (!$debug && $this->mail->SMTPDebug) {
                $this->configureMailer(false);
            }
            
            $this->mail->clearAddresses(); // Limpa os destinatários anteriores
            $this->mail->addAddress($email);
            
            $this->mail->isHTML(false); // Email em texto simples para teste
            $this->mail->Subject = $assunto;
            $this->mail->Body = $mensagem;
            
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Erro ao enviar e-mail de teste: " . $e->getMessage());
            throw $e; // Relança a exceção para capturar a mensagem de erro
        }
    }

    /**
     * Método para teste de conectividade SMTP
     * 
     * @param bool $debug Ativar modo debug
     * @return bool Retorna true se a conexão foi estabelecida com sucesso
     */
    public function testarConexaoSMTP($debug = true)
    {
        try {
            $this->configureMailer($debug);
            
            $this->mail->clearAddresses();
            $this->mail->addAddress($this->smtpUsername); // Enviar para si mesmo para teste
            
            $this->mail->isHTML(false);
            $this->mail->Subject = 'Teste de Conectividade SMTP - DIME-SC';
            $this->mail->Body = "Este é um e-mail de teste para verificar a conectividade SMTP.\n\nEnviado em: " . date('Y-m-d H:i:s');
            
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Erro no teste de conectividade SMTP: " . $e->getMessage());
            return false;
        }
    }
}
