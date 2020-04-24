<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2020/4/23
 * Time: 22:42
 */

class BlockModel extends Model{

    const BASE_PATH="/block";   //存储block上传文件，相对webroot的基础路径

    /**
     * 取相对webroot的文件存储路径，每个块记录用独立的目录存储上传文件，删除记录时需要连同目录及文件删除
     * 目录规则设id=abcdefghi，存储路径：BASE_PATH/abc/def/ghi ID长度不足前面补0
     * @param int $blockid  块记录ID
     * @param bool $make    目录不存在时自动建立
     * @throws Exception    id越界或无法创建目录时抛出错误
     * @return string   相对webroot的文件存储路径
     */
    public function getPath($blockid,$make=false){
        $blockid=intval($blockid);
        if(1>$blockid || $blockid>999999999 ) throw new Exception("块ID越界");

        $relativePath=sprintf("%09d",$blockid);
        for($pos=3; $pos<strlen($relativePath); $pos+=3){
            $relativePath=substr_replace($relativePath,'/',$pos,0);
            ++$pos;
        }
        $path =self::BASE_PATH.'/'.$relativePath;
        $physicalPath=$_SERVER['DOCUMENT_ROOT'].$path;
        if(true===$make && !is_dir($physicalPath)) {
            if (!mkdir($physicalPath, 0774, true)) throw new Exception('目录创建失败');
        }
        return $path;
    }

    /**
     * 更新块数据，删除没有链接使用的上传文件
     * @param $blockid
     * @param array $rec    //需要更新的记录字段(不包括id)，方法内不做校验，调用者需保证数据的正确性
     * @throws Exception
     */
    public function update($blockid,$rec){
        $blockid=intval($blockid);
        if(1>$blockid || $blockid>999999999 ) throw new Exception("块ID越界");

        unset($rec["id"]);  //保证ID不变
        $rt=$this->where("id=".$blockid)->save($rec);
        if(false===$rt) throw new Exception("更新失败".$this->getLastSql());

        //删除不包含在HTML连接内的文件
        $html=$rec["html"];
        $pattern = '/<img.*?src="(.*?)".*?\/?>/i';
        preg_match_all($pattern,$html,$match);
        $linkFile=array();
        foreach ($match[1] as $path){
            $linkFile[]= basename($path);
        }
        //目录中包含的文件
        $imgPath=$_SERVER['DOCUMENT_ROOT'].$this->getPath($blockid);    //物理路径
        //无法访问目录的忽略
        if(chdir($imgPath)) {
            $dirFile = array();
            $data = scandir('.');
            foreach ($data as $value){
                if($value != '.' && $value != '..'){
                    $dirFile[] = iconv("gbk","utf-8",$value);
                }
            }
            //差集
            $diff=array_diff($dirFile,$linkFile);

            //删除差集
            foreach ($diff as $file){
                $file=iconv("utf-8","gbk",$file);
                unlink($file);
            }
        }
    }

    public function remove($blockid){
        $blockid=intval($blockid);
        if(1>$blockid || $blockid>999999999 ) throw new Exception("块ID越界");

        $rt=$this->where("id=".$blockid)->limit(1)->delete();
        if(false===$rt) throw new Exception("删除失败".$this->getLastSql());

        //删除上传文件目录
        $imgPath=$_SERVER['DOCUMENT_ROOT'].$this->getPath($blockid);    //物理路径
        $this->delDirAndFile($imgPath);
    }

    /**
     * 递归删除目录和文件函数
     * @param string $dirName   要删除的路径或文件
     * @return bool
     */
    private function delDirAndFile( $dirName ) {
        $handle=null;
        try{
            if(!is_dir($dirName)) { if( !unlink($dirName)) throw new Exception("Cannot remove file:".$dirName); }
            else{
                $handle = opendir($dirName);
                if( false==$handle ) throw new Exception("Cannot open:".$dirName);
                while ( false !== ( $item = readdir( $handle ) ) ) {
                    if ( $item != "." && $item != "..") {
                        if ( is_dir( "$dirName/$item" ) )   delDirAndFile( "$dirName/$item" );  //是目录则递归调用
                        else  if(! unlink( "$dirName/$item"  ) ) throw new Exception("Cannot remove:"."$dirName/$item" );
                    }
                }
                closedir( $handle );
                if( !rmdir( $dirName ) ) throw new Exception("Cannot remove:"."$dirName" );
            }
            return true;
        }catch (Exception $e){
            if(null!=$handle) closedir( $handle );
            //var_dump($e->getMessage());
            return false;
        }
    }
}