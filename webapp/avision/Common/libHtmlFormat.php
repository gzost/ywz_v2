<?php
/**
	\brief Html标签组件
*/

class HBase
{
	//html标签的id值
	public $id = '';

	//html标签的name值
	public $name = '';

	//html标签的css值
	public $css = '';

	//html扩展的属性值,用于手动增加
	public $extAtt = '';
}

//分页控件
class HPageCtrl extends HBase
{
	//语言
	protected $lang = array('zh' => array('index' => '第%d页/共%d页', 
									'First' => '首页',
									'Last' => '上一页',
									'Next' => '下一页',
									'End' => '尾页',
									'Goto' => '转到'),
						);

	//页数,0表示没有数据
	public $pageIndex = 0;

	//总页数,0表示没有数据 $pageTotal >= $pageIndex
	public $pageTotal = 0;

	//页面跳转的Url
	public $url = '';

	//Url中的页数变量名称
	public $pageVarName = '';
    
    public function output()
    { 
	
        $pages = sprintf($this->lang['zh']['index'],$this->pageIndex,$this->pageTotal);
        $html = '';
        $html .= '<span class="pageStr">'.$pages.'</span>';      
        $html .= '<span class="firstPage"><a href="'.U($this->url,array($this->pageVarName=>1)).'">'.$this->lang['zh']['First'].'</a></span>';
        $html .= '<span class="upPage"><a href="'.U($this->url,array($this->pageVarName=>$this->pageIndex-1)).'">'.$this->lang['zh']['Last'].'</a></span>';
        $html .= '<span class="downPage"><a href="'.U($this->url,array($this->pageVarName=>$this->pageIndex+1)).'">'.$this->lang['zh']['Next'].'</a></span>';
        $html .= '<span class="lastPage"><a href="'.U($this->url,array($this->pageVarName=>$this->pageTotal)).'">'.$this->lang['zh']['End'].'</a></span>';
        $html .= '<span>'.$this->lang['zh']['Goto'].'</span>';
        
        return $html.$this->SelectPage();
    }
    
    public function SelectPage()
    {
        $select = new HSelect();
        $data = array();
        for($i=0;$i<$this->pageTotal;$i++)
        {
            $data[U($this->url,array('pageCount'=>$i+1))]= $i+1;
        }
        $select->data = $data;
        $select->selectLabel = $this->pageIndex;
        $select->name = "selectPage";
        $select->extAtt = 'onchange = "window.location.href=this.value"';
        return $select->output(); 
        
    }

}

class HSelect extends HBase
{
	//主要数据 example array(1 => '男', 2 => '女');  数组下标 为<option>标签的value值 数组值为 <option>标签的label值
	public $data = array();

	//select Value 和 selectLable 二选一
	//被选择项的value值
	public $selectValue = '';

	//被选择项的label值
	public $selectLabel = '';


	public function output()
	{
        $selectHtml = '<select name="'.$this->name.'" id="'.$this->id.'"'.$this->extAtt.' class = '.$this->css.'>';
        foreach($this->data as $index=>$value )
        {
            if($value==$this->selectLabel || $index == $this->selectValue) 
                 //保留select跳转前的值
                $selectHtml.='<option'.' selected="selected"'.' value="'.$index.'">'.$value.'</option>';
            else    
                $selectHtml.='<option value="'.$index.'">'.$this->data[$index].'</option>';   
        }
        $selectHtml.= '</select>';
        return $selectHtml;
	}
}

class HTable extends HBase
{
	//表头列名称　example array('用户名', '频道名称');
	public $head = array();

	//主要数据 example array(array('t123', 'channel'), array('t123', 'channel'), ......);
	public $body = array();

	//数据下标数组 example array('username', 'channelname');
	public $keys = array();

	//设定行数，如果０值表示有多少数据显示多少，如果大于０值表示，若数据实际行数少于设定的行数时，将会补齐行数到设置定。
	public $rows = 0;

	//生成Html代码
	public function output()
	{
	   
	   $table = '<table class="'.$this->css.'" cellpadding="0" cellspacing="0" border="0 >';
       $table.=$this->CreateHeadColumn();
       $table.=$this->CreateHtml();
       
       $table.='</table>';
       
       return $table;
	}
    
    function CreateHeadColumn()
    {
        $html='<tr class="trCss">';
        for($i=0;$i<count($this->head);$i++)
        {
            $html.='<td class="tdCss">'.$this->head[$i].'</td>';
        }
        $html.='</tr>';
        return $html;
    }    
    function CreateHtml()
    {
		$count = 0;
        $html="";
        if($this->rows == 0)
            $count = count($this->body);
        else
            $count = $this->rows;    
        
        for($i=0;$i<$count;$i++)
        {
            $html.='<tr class="trCss">';
            for($j=0;$j<count($this->keys);$j++)
            {
                
                $html.='<td class="tdCss">'.$this->body[$i][$this->keys[$j]].'</td>';
            }
            $html.='</tr>';
        }
        return $html;
    }
}
class HradioEX extends HBase
{ 
    public $isSelect;
    public $data;
 //   public $value;
//    public $label;
//    public $clickFunction = "";
    function CrteatReadio()
    {
        $html='';    
        for($i=0;$i<count($this->data);$i++)
        { 
            $html.='<input type="radio" name="'.$this->name.'" value="'.$this->data[$i]['value'].'" id = "'.$this->id.'"';
            if($this->isSelect == $this->data[$i]['value'])
            {
                $html.=' checked="checked"';
            }
            $html.=">";
            $html.='<label for="'.$this->id.'">'.$this->data[$i]['label'].'</label> ';
        }
        
        return $html;
    }
    
}
class Hradio extends HBase
{ 
    public $isSelect;
    public $value;
    public $label;
    public $id;
    public $clickFunction = "";
    function CrteatReadio()
    {
        $html='';     
        $html.='<input type="radio" name="'.$this->name.'" value="'.$this->value.'" id = "'.$this->id.'" onclick="'.$this->clickFunction.'"';
            if($this->isSelect > 0)
            {
                $html.=' checked="checked"';
            }
            $html.=">";
            $html.='<label for="'.$this->id.'">'.$this->label.'</label>';
        
        return $html;
    }
    
}
class Hchkbox extends HBase
{
    public $data;   
    public $isSelect;
    public $value;
    public $label;
    public $id;
    public $clickFun;
    function CrteatCheckBox()
    {
        
        $html='';
        
            $html.='<input type="checkbox" name="'.$this->name.'" value="'.$this->value.'" id = "'.$this->id.'" onclick="'.$this->clickFun.'"';
           //$html.='<input type="checkbox" name="'.$this->name.'" value="'.$this->value.'"';
            if($this->isSelect > 0)
            {
                $html.=' checked="checked"';
            }
            
            $html.=">";
            $html.='<label for="'.$this->id.'">'.$this->label.'</label>';
       //  $a = "asd";
       //  echo $a.$html;
        return $html;
    } 
}
class HchkboxEX extends HBase
{
    public $data;   
    public $isSelect;
    function CrteatCheckBox()
    {
        
        $html='';
        for($i=0;$i<count($this->data);$i++)
        {
            $html.='<input type="checkbox" name="'.$this->name.'" value="'.$this->data[$i]['value'].'" id = "'.$this->id.'" onclick="'.$this->data[$i]['clickFun'].'"';
           //$html.='<input type="checkbox" name="'.$this->name.'" value="'.$this->value.'"';
            if($this->isSelect == $this->data[$i]['value'])
            {
                $html.=' checked="checked"';
            }
            
            $html.=">";
            $html.='<label for="'.$this->id.'">'.$this->data[$i]['label'].'</label>';
        }
       //  $a = "asd";
       //  echo $a.$html;
        return $html;
    } 
}

?>