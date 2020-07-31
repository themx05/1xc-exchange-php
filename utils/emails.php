<?php
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;
    use PHPMailer\PHPMailer\SMTP;

    function sendVerificationEmail(string $user, string $emailAddress, string $code): bool{
        global $email, $logger;
        
        $builder = new StringBuilder(TEMPLATE_DIR."/email_validation.temp.html","fr");
        $builder->set("code",$code);
        $builder->set("user", $user);
        $builder->set("mail", $emailAddress);

        try{
            $mailer = new PHPMailer();
            $mailer->isSMTP();
            $mailer->SMTPAuth = true;
          //  $mailer->SMTPSecure = true;
            $mailer->Host = gethostname();
            $mailer->Port = $email['port'] ;
            $mailer->setFrom($email['user']);
            $mailer->FromName="1xcrypto";
            $mailer->Username = $email['user'];
            $mailer->Password = $email['password'];
            $mailer->addAddress($emailAddress);
            $mailer->Subject = "Validation de compte";
            $mailer->msgHTML($builder->render()); /// Replace with template 
            $logger->info("Sending Email to: ".$emailAddress);
            $result = $mailer->send();
            $logger->info("Email sent: ".($result == true?1:0));
            return $result;
        }catch(Exception $e){
            $logger->error("Encountered Error: ".json_encode($e));
            return false;
        }
    }

    function sendMaintenanceEmail(string $address):bool{
        global $email, $logger;
        
        $builder = new StringBuilder(TEMPLATE_DIR."/switch_to_maintenance.html","fr");

        try{
            $mailer = new PHPMailer();
            $mailer->isSMTP();
            $mailer->SMTPAuth = true;
            $mailer->Host = gethostname();
            $mailer->Port = $email['port'] ;
            $mailer->setFrom($email['user']);
            $mailer->FromName="1xcrypto";
            $mailer->Username = $email['user'];
            $mailer->Password = $email['password'];
            $mailer->addAddress($address);
            $mailer->Subject = "Mise a niveau systeme";
            $mailer->msgHTML($builder->render()); /// Replace with template 
            $result = $mailer->send();
            $logger->info("Email sent: ".($result == true?1:0));
            return $result;
        }catch(Exception $e){
            $logger->info("Encountered Error: ".json_encode($e));
            return false;
        }
    }
?>