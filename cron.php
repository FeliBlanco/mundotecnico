<?php

$url = "https://myweb.mundotecnico.info/phpbb/mpfeli/mercadopago/verificar_pms";

$ch = curl_init();


curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);

if(curl_errno($ch)) {
    echo "Error en cURL: " . curl_error($ch);
    curl_close($ch);
    exit;
}
echo "E";

curl_close($ch);

?>