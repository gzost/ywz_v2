<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2020/7/26
 * Time: 22:19
 */


//test
$gd=new gdPaint(array('height'=>30,'width'=>90));
$text=$gd->generateString(4,false);
$gd->verifyPicture($text);

/**
 * Class gdPaint
 * 运用GD库画图的类
 */
class gdPaint {
    private $para=array(
        'width'=>200,   //输出图片的宽度
        'height'=>80,    //输出图片高度
        'fontfile' => __DIR__.'/font/Courier.ttf' //字体文件的绝对路径(必须)，文件大于3M为中文字体文件
    );

    //构造函数可设置参数
    function __construct($para=array())  {
        $this->para=array_merge($this->para, $para);
    }

    /**
     * 生成一个随机字串
     * @param int $len  字串长度
     * @param bool $alphabet    是否包含字母
     * @return string
     */
    public function generateString($len=4,$alphabet=true){
        $str=($alphabet)?'qwertyuipasdfghjklzxcvbnmQWERTYUIPASDFGHJKLZXCVBNM123456789':'1234567890';
        $text='';
        $keylen=strlen($str)-1;
        for($i=0; $i<$len; $i++) $text .= $str[mt_rand(0, $keylen)];
        return $text;
    }

    /**
     * 以图片形式输出字串，图片中增加识别干扰，用于确认“人”在操作的验证
     * @param $text
     * @param string $type
     */
    public function verifyPicture($text,$type='png'){
        //1、建立画布
        $img = imagecreatetruecolor($this->para['width'], $this->para['height']);
        imageantialias($img, true); //消除锯齿
        //imagesetthickness($img,5);
        //2、生成并填充背景色
        $bgc = imagecolorallocate($img, 220, 220, 200);
        imagefill($img, 0, 0, $bgc);
        //3、用随机的颜色在随机的位置生成20条线段
        for ($i = 0; $i < 10; $i++) {
            $x1 = mt_rand(0, $this->para['width']-1);
            $y1 = mt_rand(0, $this->para['height']-1);
            //$x2 = mt_rand(0, 200);
            $x2 = $x1+$this->para['height']-mt_rand(0, $this->para['height']*2);   //控制线条不要拉得太长
            $y2 = mt_rand(0, $this->para['height']-1);

            $red = mt_rand(0, 255);
            $green = mt_rand(0, 255);
            $blue = mt_rand(0, 255);
            $color = imagecolorallocate($img, $red, $green, $blue);
            imageline($img, $x1, $y1, $x2, $y2, $color);
            if($x1>$x2){
                $x1++; $x2; $y1++; $y2++;
            }else{
                $x1--; $x2; $y1++; $y2++;
            }
            //imageline($img, $x1, $y1, $x2, $y2, $color);
        }
//4、用随机的颜色在随机的位置生成100个像素点
        for ($i = 0; $i < 1; $i++) {
            $x = mt_rand(0, $this->para['width']-1);
            $y = mt_rand(0, $this->para['height']-1);
            $color = imagecolorallocate($img, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
            imagesetpixel($img, $x, $y, $color);//像素点小可以使用圆
        }
//5、用随机的颜色在随机的位置生成验证码文字
        $len=strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $size = $this->para['height']/1.5;//单位 px 大小为高度的一半
            $angle = mt_rand(-30, 30);//水平x轴为0度
            $x =5+ $i *( $this->para['width'] -5) / $len;
            $y = $this->para['height']*3/4; //文字左下角为锚点(x,y) x平分画布的宽度，y为画布高度的3/4，
            $color = imagecolorallocate($img, mt_rand(0, 200), mt_rand(0, 200), mt_rand(0, 200));

            //$fontfile = __DIR__.'/font/Courier.ttf';//字体文件的绝对路径(必须)，文件大于3M为中文字体文件
            $fontfile = $this->para['fontfile'];
            //$str = 'qwertyuipasdfghjklzxcvbnmQWERTYUIPASDFGHJKLZXCVBNM123456789';
            //$text = $str[mt_rand(0, strlen($str)-1)];
            imagettftext($img, $size, $angle, $x, $y, $color, $fontfile,$text[$i] );
        }
//6、保存
        header("content-type:image/png");//网页内容为图片类型
        header("X-tag:".$text);
        imagepng($img);//只有第一个参数，不会保存但显示在页面。
//7、销毁
        imagedestroy($img);
    }
}