<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2018-5-23
 * Time: 15:55
 * 图片压缩类：通过缩放来压缩。
 * 如果要保持源图比例，把参数$percent保持为1即可。
 * 即使原比例压缩，也可大幅度缩小。数码相机4M图片。也可以缩为700KB左右。如果缩小比例，则体积会更小。
 *
 * 结果：可保存、可直接显示。
 * 需要GD库
 */

class Imgcompress
{
    private $src;
    private $image;
    private $imageinfo;
    private $percent = 0.5;
    private $border=0;
    private $quality=90;    //jpeg图像质量
    /**
     * 图片压缩
     * @param $src 源图
     * @param float $percent  压缩比例
     */
    public function __construct($src, $percent=1)
    {
        $this->src = $src;
        $this->percent = $percent;
    }
    /** 高清压缩图片
     * @param string $saveName  提供图片名（可不带扩展名，用源图扩展名）用于保存。或不提供文件名直接显示
     * @param int border 图片长边像素。定义参数$percent会被忽略，原图长边小于此数值，则不做调整。
     */
    public function compressImg($saveName='',$border=0)
    {
        $this->border=$border;
        $this->_openImage();
        if(!empty($saveName)) $this->_saveImage($saveName);  //保存
        else $this->_showImage();
    }
    /**
     * 内部：打开图片
     */
    private function _openImage()
    {
        list($width, $height, $type, $attr) = getimagesize($this->src);
        $this->imageinfo = array(
            'width'=>$width,
            'height'=>$height,
            'type'=>image_type_to_extension($type,false),
            'attr'=>$attr
        );
        $fun = "imagecreatefrom".$this->imageinfo['type'];
        $this->image = $fun($this->src);
        $this->_thumpImage();
    }
    /**
     * 内部：操作图片
     */
    private function _thumpImage()
    {
        if($this->border>0){
            $new_width = $this->imageinfo['width'] ;
            $new_height = $this->imageinfo['height'] ;
            if($this->imageinfo['width'] > $this->imageinfo['height']){
                if($this->imageinfo['width'] > $this->border){
                    $new_width=$this->border;
                    $new_height=$this->imageinfo['height']*((double)$this->border/(double)$this->imageinfo['width']);
                }
            }else{
                if($this->imageinfo['height'] > $this->border){
                    $new_height=$this->border;
                    $new_width=$this->imageinfo['width']*((double)$this->border/(double)$this->imageinfo['height']);
                }
            }
        }else{
            $new_width = $this->imageinfo['width'] * $this->percent;
            $new_height = $this->imageinfo['height'] * $this->percent;
        }


        $image_thump = imagecreatetruecolor($new_width,$new_height);
        //将原图复制带图片载体上面，并且按照一定比例压缩,极大的保持了清晰度
        imagecopyresampled($image_thump,$this->image,0,0,0,0,$new_width,$new_height,$this->imageinfo['width'],$this->imageinfo['height']);
        imagedestroy($this->image);
        $this->image = $image_thump;
    }
    /**
     * 输出图片:保存图片则用saveImage()
     */
    private function _showImage()
    {
        header('Content-Type: image/'.$this->imageinfo['type']);
        $funcs = "image".$this->imageinfo['type'];
        $funcs($this->image);
    }
    /**
     * 保存图片到硬盘：
     * @param  string $dstImgName  1、可指定字符串不带后缀的名称，使用源图扩展名 。2、直接指定目标图片名带扩展名。
     */
    private function _saveImage($dstImgName)
    {
        if(empty($dstImgName)) return false;
        $allowImgs = array('.jpg', '.jpeg', '.png', '.bmp', '.wbmp','.gif');   //如果目标图片名有后缀就用目标图片扩展名 后缀，如果没有，则用源图的扩展名
        $dstExt =  strrchr($dstImgName ,".");
        $sourseExt = strrchr($this->src ,".");
        if(!empty($dstExt)) $dstExt =strtolower($dstExt);
        if(!empty($sourseExt)) $sourseExt =strtolower($sourseExt);
        //有指定目标名扩展名
        if(!empty($dstExt) && in_array($dstExt,$allowImgs)){
            $dstName = $dstImgName;
        }elseif(!empty($sourseExt) && in_array($sourseExt,$allowImgs)){
            $dstName = $dstImgName.$sourseExt;
        }else{
            $dstName = $dstImgName.$this->imageinfo['type'];
        }
        $funcs = "image".$this->imageinfo['type'];
//var_dump($funcs,$dstName);
        $funcs($this->image,$dstName,$this->quality);
    }
    /**
     * 销毁图片
     */
    public function __destruct(){
        imagedestroy($this->image);
    }
}
/*
$source =  'd:/AT.JPG';//原图片名称
$dst_img = 'D:\MyProject\ylh\webroot\room\at_s.jpg';//压缩后图片的名称
$percent = 0.2;  #原图压缩，不缩放，但体积大大降低
$image = new imgcompress($source,$percent);
$image->compressImg($dst_img,250);
$dst_img = 'D:\MyProject\ylh\webroot\room\at_m.jpg';//压缩后图片的名称
$image->compressImg($dst_img,550);
*/
?>