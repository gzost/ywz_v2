<?php
$table = '';
$table .= "<table>
                <thead>
                    <tr>
                        <th class='name'>公司</th>
                        <th class='name'>汽车</th>
                        <th class='name'>ccc</th>
                        <th class='name'>ddd</th>
                        <th class='name'>ggg</th>
                        <th class='name'>rrr</th>
                        <th class='name'>uu</th>
                    </tr>
                </thead>
                <tbody>";
 {
    $table .= "<tr>
                        <td class='name'>1</td>
                        <td class='name'>2</td>
                        <td class='name'>3</td>
                        <td class='name'>4</td>
                        <td class='name'>方法</td>
                        <td class='name'>6661234567890</td>
                        <td class='name'>677</td>
                    </tr>";
}
$table .= "</tbody>
            </table>";
//通过header头控制输出excel表格
header("Pragma: public");
header("Expires: 0");
header("Cache-Control:must-revalidate, post-check=0, pre-check=0");
header("Content-Type:application/force-download");
header("Content-Type:application/vnd.ms-execl");
header("Content-Type:application/octet-stream");
header("Content-Type:application/download");;
header('Content-Disposition:attachment;filename="入库明细表.xls"');
header("Content-Transfer-Encoding:binary");
echo iconv('utf-8','gbk',$table);

die();
$pattern="/^[a-zA-Z0-9_-]{6,16}$/";
$r=preg_match($pattern,'aabb77');
var_dump($r);

echo date("Y-m-d H:i:s",1537451065);

die("===");
$html = file_get_contents('http://demo.av365.cn:8011/stat');
//$html="<xml><uptime>83258</uptime><naccepted>2004</naccepted><bw_in>1775008</bw_in></xml>";

//libxml_disable_entity_loader(true);
$xml=simplexml_load_string($html, 'SimpleXMLElement', LIBXML_NOCDATA);
$data = json_decode(json_encode($xml),TRUE);
var_dump($data);
?>