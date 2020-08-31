<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2020/8/12
 * Time: 10:05
 * 股票分析
 */

/**
 * Class StockAction
 * 大多数证券公司的行情软件都会使用通达信的引擎，因此数据存储格式是相同的：
 * 数据目录：<安装路径>/vipdoc/[sz|sh|ot|ds|cw]/[lday|minline...]
 * 其中：sz-深圳市场，sh-上海市场
 * 日线在lday目录内
 * 通达信日线*.day文件，文件名即股票代码
每32个字节为一天数据
每4个字节为一个字段，每个字段内低字节在前
00 ~ 03 字节：年月日, 整型
04 ~ 07 字节：开盘价*1000， 整型
08 ~ 11 字节：最高价*1000,  整型
12 ~ 15 字节：最低价*1000,  整型
16 ~ 19 字节：收盘价*1000,  整型
20 ~ 23 字节：成交额（元），float型
24 ~ 27 字节：成交量（手），整型
28 ~ 31 字节：上日收盘*1000, 整型
 *
 * 通达信5分钟线*.5文件
每32个字节为一个5分钟数据，每字段内低字节在前
00 ~ 01 字节：日期，整型，设其值为num，则日期计算方法为：
year=floor(num/2048)+2004;
month=floor(mod(num,2048)/100);
day=mod(mod(num,2048),100);
02 ~ 03 字节： 从0点开始至目前的分钟数，整型
04 ~ 07 字节：开盘价（分），整型
08 ~ 11 字节：最高价（分），整型
12 ~ 15 字节：最低价（分），整型
16 ~ 19 字节：收盘价（分），整型
20 ~ 23 字节：成交额（元），float型
24 ~ 27 字节：成交量（股）
28 ~ 31 字节：（保留）
 */
class StockAction extends Action{
    const DATAFILEPATH="C:/zd_zsone/vipdoc";
    const RECORDLEN=32; //记录长度，byte

    public function fixedInv($func=""){
        if(!empty($func) && method_exists(__CLASS__,$func)) $this->$func();
        else{
            $webVar=array();
            $this->assign($webVar);
            $this->display("fixedInv");
        }

    }
    private function fixedInvCalc(){
        $market=$_POST["market"];
        $stockCode=$_POST["stockCode"];
        $begindate=intval($_POST["begindate"]);
        $enddate=intval($_POST["enddate"]);
        $skipday=$_POST["skipday"];
        $amt=$_POST["amt"]*10000;
        $unit=$_POST["unit"]*10000;
        $yield=$_POST["yield"]; //目标收益率%

        $filePath=self::DATAFILEPATH.'/'.$market.'/lday/'.$market.$stockCode.'.day';
        if(!is_file($filePath)){
            echo "找不到数据文件：".$filePath;
            return;
        }
        $this->write( "<pre>正在分析：$filePath<br>");
        try{
            $handle=fopen($filePath,"r");

            //定位到开始日期
            do{
                $data=fread($handle,self::RECORDLEN);
                if(false===$data) throw new Exception("找不到开始日期的数据");
                $rec=$this->decode($data);
                //$this->write('.');
            }while($rec["date"]<$begindate);
            //初始化资金
            $pool=0;    //积累的盈利
            $funds=array(
                "cash"=>$amt,   //现金
                "in"=>0,    //已购买股票的资金
                "stock"=>0,     //股票数量
                "value"=>0,     //股票市值
                "assets"=>$amt  //总资产=cash+value
            );
            $md=0;  //资金占用万元*日
            do{
                //按$rec数据进行投资分析
                $note="";

                //1、计算当前收益率

                $nowYield=(0==$funds['in'])?0:($funds['value']-$funds['in'])/$funds['in']*100;
                if($nowYield>=$yield){
                    //达到收益目标，卖出全部股票
                    /*
                    $pool += ($funds['value']-$funds['in']);
                    $note="达到收益目标, 累计收益".$pool;
                    $funds['cash'] += $funds['value'];
                    $funds['in']=$funds['stock']=$funds['value']=0;
                    $funds['assets']=$funds['cash'];
                    */
                    //卖出一半股票
                    $sell=$funds['value']/2; //卖出一半股票获得的资金
                    $pool += ($funds['value']-$funds['in'])/2;
                    $note="达到收益目标, 累计收益".$pool;
                    $funds['cash'] += $sell;
                    $funds['in'] = $funds['value']/2;   //相当于清仓后再买回一半的股票
                    $funds['stock']=$funds['stock']/2;
                    $funds['value']=$funds['value']/2;
                    $funds['assets']=$funds['cash']+$funds['value'];
                }else{
                    //购买股票
                    if($funds['cash']<$unit) $note="资金不足";
                    else {

                        $buy = floor($unit / $rec['close'] / 100) * 100; //可购入股票数量,固定金额
                        //$buy = 6000;  //固定股数
                        $value = $buy * $rec['close'];  //使用资金
                        $funds['cash'] -= $value;
                        $funds['in'] += $value;
                        $funds['stock'] += $buy;
                    }
                    $funds['value'] =$funds['stock']*$rec['close']; //股票市值
                    $funds['assets'] =$funds['cash']+$funds['value'];
                }
                /*
                if($funds["cash"]==0) $nowYield=0;
                else $nowYield=($funds['value']/$funds['cash']-1)*100;
                //echo $nowYield;
                if($nowYield>=$yield){
                    //达到收益目标，卖出全部股票
                    $pool += ($funds['assets']);
                    $note="达到收益目标".$pool;
                    $funds['cash']=$funds['assets']=0;
                    $funds["stock"]=$funds["value"]=0;
                }else{
                    //购买股票
                    if(false) $note="资金不足";
                    else{
                        $buy=floor($unit/$rec['close']/100)*100; //可购入股票数量
                        $value=$buy*$rec['close'];  //使用资金
                        $funds['cash'] +=$value;
                        $funds['stock'] +=$buy;
                        $funds['value'] =$funds['stock']*$rec['close']; //股票市值
                        $funds['assets'] =$funds['value'];
                    }
                }
                */
                $md += $funds['in']/10000*$skipday;
                //echo $md;

                printf("日期：%d\t收盘价：%.3f\t现金：%10.2f\t投入：%10.2f\t市值：%10.2f\t总资产：%10.2f\t%s\n",
                    $rec['date'],$rec['close'],$funds['cash'],$funds['in'],$funds['value'],$funds['assets'],$note);
                $rt=fseek($handle,$skipday*self::RECORDLEN,SEEK_CUR );
                //var_dump($rt);
                if(-1==$rt) throw new Exception("没有更多的分析数据了");    //跳过一个投资周期的天数
                $data=fread($handle,self::RECORDLEN);
                //var_dump($data);
                if(empty($data)) throw new Exception("没有更多的分析数据了");
                $rec=$this->decode($data);
            }while($rec["date"]<=$enddate);
            fclose($handle);
        }catch (Exception $e){
            echo $e->getMessage()." 处理终止<br>";
            //return;
        }
        $total=$funds['assets'];
        $p=$total-$amt; //总收益
        printf("最后总资产：%.2f\t总收益：%.2f\t万元日收益：%.2f\t年化收益率：%.2f%%\t  收益率：%.2f%%\n",
            $total,$p,$p/$md,$p/$md*250/100, ($total/$amt-1)*100);
        echo "处理结束<br></pre>";


    }

    private function write($str){
        echo $str;
        ob_flush();
        flush();
    }

    //解码一条记录
    private function decode($data){
        $rec=array();
        $rec["date"]=$this->bin2int($data,0);   //日期
        $rec["open"]=$this->bin2int($data,4)/1000;   //开盘价
        $rec["height"]=$this->bin2int($data,8)/1000; //最高价
        $rec["low"]=$this->bin2int($data,12)/1000;    //最低价
        $rec["close"]=$this->bin2int($data,16)/1000;  //收盘价
        $rec["turnover"]=$this->bin2int($data,20);   //成交额
        $rec["valume"]=$this->bin2int($data,24); //成交量
        $rec["preclose"]=$this->bin2int($data,28)/1000; //上日收盘,没数据
        return $rec;
    }
    private function bin2int($data,$start){
        $end=$start+3;
        $val=0;
        for($i=$end; $i>$start; $i--){
            $val += ord($data[$i]);
            $val = $val<<8;
        }
        $val += ord($data[$i]);
        return $val;
    }
}