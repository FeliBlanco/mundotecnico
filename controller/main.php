<?php
namespace mpfeli\mercadopago\controller;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use MercadoPago\SDK;
use Symfony\Component\HttpFoundation\Response;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
require 'ext/mpfeli/mercadopago/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require 'ext/mpfeli/mercadopago/vendor/phpmailer/phpmailer/src/Exception.php';


require 'ext/mpfeli/mercadopago/vendor/autoload.php';

class main
{
	protected $config;
	protected $helper;
	protected $template;
	protected $user;
    protected $db;
    protected $emailer;

    public function __construct(\phpbb\config\config $config, \phpbb\controller\helper $helper, \phpbb\template\template $template, \phpbb\user $user, \phpbb\db\driver\driver_interface $db, \PHPMailer\PHPMailer\PHPMailer $phpmailer)
    {
		$this->config = $config;
		$this->helper = $helper;
		$this->template = $template;
		$this->user = $user;
        $this->db = $db;
        $this->phpmailer = $phpmailer;
        $this->phpmailer->Hostname = $this->config['server_name'];

        $this->ID_GROUP = 8;
        $this->ID_GROUP_DEFAULT = 2;
        $this->TABLA_USER_GROUP = "phpbb9f_user_group";
        $this->TABLA_USER = "phpbb9f_users";

        \MercadoPago\SDK::setAccessToken('TEST-1178374229100737-102500-170813faaa009167ed68d4070cc2a46c-544629529');
    }

    public function send_email($to_email, $subject, $message, $html = null, $vars = null)
    {
        file_put_contents('ext/mpfeli/mercadopago/error_jeje.log', "PRUEBA\n", FILE_APPEND);
        $mail = $this->phpmailer;

        try {
            $mail->isSMTP();
            $mail->Host = 'webmail.mundotecnico.info';
            $mail->Port = 25;
            $mail->SMTPAuth = true;
            $mail->Username = 'notificaciones@mundotecnico.info';
            $mail->Password = 'Chispita**66';
            $mail->SMTPSecure = false;
            $mail->SMTPAutoTLS = true;
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];

            $mail->setFrom('notificaciones@mundotecnico.info', 'Mundo Tecnico');
            $mail->addAddress($to_email);

            if($html && file_exists($html)) {
                file_put_contents('ext/mpfeli/mercadopago/error_jeje.log', "EXISTE HTML\n", FILE_APPEND);
                $htmlContent = file_get_contents($html);
            } else {
                file_put_contents('ext/mpfeli/mercadopago/error_jeje.log', "NO EXISTE HTML\n", FILE_APPEND);
                $htmlContent = null;
            }

            if($vars && is_array($vars)) {
                foreach ($vars as $key => $value) {
                    $message = str_replace("{{" . $key . "}}", $value, $message);
                    if($htmlContent) {
                        $htmlContent = str_replace("{{" . $key . "}}", $value, $htmlContent);
                    }
                }
            }
        
        
            $mail->Subject = $subject;
            if($htmlContent) {
                $mail->Body = $htmlContent;
                //$mail->AltBody = strip_tags($htmlContent);
            } else {
                $mail->Body = nl2br($message);
                //$mail->AltBody = $message;
            }
            $mail->isHTML(true);
            $mail->IsHTML(true);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log($mail->ErrorInfo);
            return false;
        }
    }

    public function handle()
    {
        try {
            return $this->helper->render('elegir_pago.html', "Comprar premium");
        }
        catch(\Exception $e) {
            error_log($e->getMessage());
            return new Response('Ha ocurrido un error interno', 500);
        }
    }

    public function screen_argentina() {
        try {
            return $this->helper->render('mercadopago_page.html', "Comprar premium");
        }
        catch(\Exception $e) {
            error_log($e->getMessage());
            return new Response('Ha ocurrido un error interno', 500);
        }        
    }
    public function screen_pp() {
        try {
            return $this->helper->render('pp_page.html', "Comprar premium");
        }
        catch(\Exception $e) {
            error_log($e->getMessage());
            return new Response('Ha ocurrido un error interno', 500);
        }        
    }

    
    public function verificar_pms() {
        $vencidos = $this->db->sql_query("SELECT p.*, u.user_email, u.username FROM pagos p INNER JOIN ".$this->TABLA_USER." u ON u.user_id = p.id_usuario WHERE p.fecha_vencimiento <= CURDATE() AND p.vencido = 0");
        if($vencidos) {
            while($pago = $this->db->sql_fetchrow($vencidos)) {
                $this->db->sql_query("DELETE FROM ".$this->TABLA_USER_GROUP." WHERE user_id = ".$pago['id_usuario']." AND group_id = ".$this->ID_GROUP);
                $this->db->sql_query("UPDATE pagos SET vencido = 1 WHERE id = ".$pago['id']);
                $this->db->sql_query("UPDATE ".$this->TABLA_USER." SET group_id = ".$this->ID_GROUP_DEFAULT.", user_colour = '' WHERE user_id = ".$pago['id_usuario']);
                $this->send_email($pago['user_email'], 'Vencimiento', '', "ext/mpfeli/mercadopago/templates/email_baja.html", ["nombre_usuario" => $pago['username']]);
            }
        }
        $result = $this->db->sql_query("SELECT P.*, U.user_email, U.username, P.id AS pago_id FROM pagos P INNER JOIN ".$this->TABLA_USER." U ON U.user_id = P.id_usuario WHERE P.vencido = 0 AND ((DATEDIFF(P.fecha_vencimiento, CURDATE()) <= 7 AND P.notificacion = 0)  OR (DATEDIFF(P.fecha_vencimiento, CURDATE()) <= 1 AND P.notificacion != 2))");
        if($result) {
            while($user = $this->db->sql_fetchrow($result)) {
                $email = $user['user_email'];
                $username = $user['username'];
                $dias_faltantes = (new \DateTime($user['fecha_vencimiento']))->diff(new \DateTime())->days;

                if($dias_faltantes <= 1 && $user['notificacion'] != 2) {
                    $this->send_email($email, "Recordatorio de vencimiento", "", "ext/mpfeli/mercadopago/templates/email_un_dia.html", ["nombre_usuario" => $username, "fecha_vencimiento" => $user['fecha_vencimiento'], "redirect" => "https://myweb.mundotecnico.info/phpbb/cuenta_premium"]);

                    $this->db->sql_query("UPDATE pagos SET notificacion = 2 WHERE id = " . $user['pago_id']);
                }
                else if ($dias_faltantes <= 7 && $user['notificacion'] != 1) {
                    $this->send_email($email, "Recordatorio de vencimiento", "", "ext/mpfeli/mercadopago/templates/email_una_semana.html", ["nombre_usuario" => $username, "fecha_vencimiento" => $user['fecha_vencimiento'], "redirect" => "https://myweb.mundotecnico.info/phpbb/cuenta_premium"]);

                    $this->db->sql_query("UPDATE pagos  SET notificacion = 1 WHERE id = " . $user['pago_id']);
                }
            }
        }
        return new Response('Hola', 200);
    }

    public function webhook() {
        $request = json_decode(file_get_contents('php://input'), true);

        if($request['action'] === "payment.created") {
            $payment_id = $request['data']['id'];
            $payment = \MercadoPago\Payment::find_by_id($payment_id);
            if($payment) {
                $status = $payment->__get('status');
                if($status == "approved") {

                }

                $metadata = $payment->__get('metadata');
                
                $user_id = $metadata->user_id;
                $product_id = $metadata->product_id;

                $result_user = $this->db->sql_query("SELECT user_email, username FROM ".$this->TABLA_USER." WHERE user_id = ".$user_id);
                if($row = $this->db->sql_fetchrow($result_user)) {
                    $email = $row['user_email'];
                    $username = $row['username'];
                }
                               
                $dia_inicio =  date("Y-m-d");
                $data_ex = "";

                /*if($product_id == 1) {
                    $data_ex =  date('y-m-d', strtotime($dia_inicio. ' + 1 month'));
                } else if($product_id == 2) {
                    $data_ex =  date('y-m-d', strtotime($dia_inicio. ' + 6 month'));
                } else if($product_id == 3) {
                    $data_ex =  date('y-m-d', strtotime($dia_inicio. ' + 1 year'));
                }*/
                // 1
                //6 meses
                //anual
                //una semana y un dia antes aviso por mail
                $data_ex =  date('y-m-d', strtotime($dia_inicio. ' + 2 day'));

                $fecha_actual = new \DateTime();
                $fecha_extendida = new \DateTime($data_ex);
                $diferencia = $fecha_actual->diff($fecha_extendida);
                $dias_restantes = $diferencia->days;

                
                $this->send_email($email, 'Compra de premium', '', "ext/mpfeli/mercadopago/templates/email.html", ["nombre_usuario" => $username, "dias_suscripcion" => $dias_restantes, "fecha_vencimiento" => $data_ex]);

                /*$result = $this->db->sql_query('SELECT * FROM phpbb9f_users');

                while($row = $result->fetch_array()) {
                    print_r($row);
                }*/

                $sql_check = 'SELECT COUNT(*) as count FROM '.$this->TABLA_USER_GROUP.' WHERE user_id = ' . intval($user_id) . ' AND group_id = '.$this->ID_GROUP;
                $result_check = $this->db->sql_query($sql_check);
                $row = $this->db->sql_fetchrow($result_check);
                if($row['count'] == 0) {
                    $this->db->sql_query('INSERT INTO '.$this->TABLA_USER_GROUP.' (user_id, group_id, user_pending) VALUES ('.$user_id.', '.$this->ID_GROUP.', 0)');
                }
                $result = $this->db->sql_query("UPDATE ".$this->TABLA_USER." SET group_id = ".$this->ID_GROUP.", user_colour = 'FF4000' WHERE user_id = " . $user_id);

                $this->db->sql_query("UPDATE pagos SET vencido = 1 WHERE id_usuario = ".$user_id);
                $this->db->sql_query("INSERT INTO pagos (id_usuario, nro_operacion, fecha_pago, fecha_vencimiento) VALUES (".$user_id.", '".$payment_id."', '".$dia_inicio."', '".$data_ex."')");

                /*$result = $this->db->sql_query('SELECT * FROM phpbb9f_groups');
                while($row = $result->fetch_array()) {
                    file_put_contents('ext/mpfeli/mercadopago/webhook_debug.log', print_r($row).'\n', FILE_APPEND);
                }*/

                
                //$result = $this->db->sql_query('UPDATE phpbb9f_users SET group_id = 11  WHERE user_id = ' . $user_id);
            }
        }
        return new Response('', 200);
    }

    public function create_preference()
    {
        $request = json_decode(file_get_contents('php://input'), true);
        $product_id = $request['product_id'] ?? null;
        $price = null;

        switch($product_id) {
            case 1: {
                $price = 28000;
                break;
            }
            case 2: {
                $price = 20000;
                break;
            }
            case 3: {
                $price = 12000;
                break;
            }
        }

        if (!$product_id) {
            return new \Symfony\Component\HttpFoundation\JsonResponse(
                ['error' => 'Datos insuficientes.'],
                400
            );
        }

        try {
            
            $preference = new \MercadoPago\Preference();

            $item = new \MercadoPago\Item();
            $item->title = "Cuenta Premium";
            $item->quantity = 1;
            $item->unit_price = (float) $price;
            $preference->items = [$item];

            $preference->external_reference = $this->user->data['user_id'];
            $preference->metadata = Array(
                "user_id" => $this->user->data['user_id'],
                "product_id" => $product_id
            );

            $preference->notification_url = 'https://myweb.mundotecnico.info/phpbb/mpfeli/mercadopago/webhook';

            $preference->save();

            return new \Symfony\Component\HttpFoundation\JsonResponse([
                'init_point' => $preference->init_point,
            ], 200);
        } catch (\Exception $e) {
            error_log($e->getMessage());
            return new \Symfony\Component\HttpFoundation\JsonResponse(
                ['error' => 'Error al generar la preferencia.'],
                500
            );
        }
    }   
}
