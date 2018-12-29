<?php


namespace app\wallet\service;

//邮件发送
class Email
{

    /**
     * @param $subject          string 主题
     * @param array $to         array  收件人
     * @param $body             string 内容
     * @param string $charSet   string 默认编码
     * @param string $contentType
     * @return bool|int
     */
     public static function sendEmail($subject,array $to,$body,$charSet='utf-8',$contentType='text/html'){
        $config         = config('email');
        try{
            $transport  = \Swift_SmtpTransport::newInstance($config['host'],$config['port'],$config['security'])
                ->setUsername($config['username'])
                ->setPassword($config['password']);
            $mailer     = \Swift_Mailer::newInstance($transport);
            $message    = \Swift_Message::newInstance()
                ->setFrom(array($config['username'] => $config['nickname']))
                ->setTo($to)
                ->setSubject($subject)
                ->setCharset($charSet)
                ->setContentType($contentType)
                ->setBody($body);
            return $mailer->send($message);
        }catch (\Exception $e){
            dump($e->getMessage());
            return false;
        }
    }

     public static function sendEmail2($subject,array $to,$body,$charSet='utf-8',$contentType='text/html'){
        $config         = config('email2');
        try{
            $transport  = \Swift_SmtpTransport::newInstance($config['host'],$config['port'],$config['security'])
                ->setUsername($config['username'])
                ->setPassword($config['password']);

            $mailer     = \Swift_Mailer::newInstance($transport);
            $message    = \Swift_Message::newInstance()
                ->setFrom(array($config['username'] => $config['nickname']))
                ->setTo($to)
                ->setSubject($subject)
                ->setCharset($charSet)
                ->setContentType($contentType)
                ->setBody($body);
            return $mailer->send($message);
        }catch (\Exception $e){
            dump($e->getMessage());
            return false;
        }
    }

     public static function sendEmail3($subject,array $to,$body,$charSet='utf-8',$contentType='text/html'){
        $config         = config('email3');
        try{
            $transport  = \Swift_SmtpTransport::newInstance($config['host'],$config['port'],$config['security'])
                ->setUsername($config['username'])
                ->setPassword($config['password']);

            $mailer     = \Swift_Mailer::newInstance($transport);
            $message    = \Swift_Message::newInstance()
                ->setFrom(array($config['username'] => $config['nickname']))
                ->setTo($to)
                ->setSubject($subject)
                ->setCharset($charSet)
                ->setContentType($contentType)
                ->setBody($body);
            return $mailer->send($message);
        }catch (\Exception $e){
            dump($e->getMessage());
            return false;
        }
    }
}