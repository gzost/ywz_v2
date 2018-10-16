<?php
//var_dump($_SERVER);
echo date("Y-m-d H:i:s",1537451065);

die("===");
$html = file_get_contents('http://demo.av365.cn:8011/stat');
//$html="<xml><uptime>83258</uptime><naccepted>2004</naccepted><bw_in>1775008</bw_in></xml>";

//libxml_disable_entity_loader(true);
$xml=simplexml_load_string($html, 'SimpleXMLElement', LIBXML_NOCDATA);
$data = json_decode(json_encode($xml),TRUE);
var_dump($data);
?>