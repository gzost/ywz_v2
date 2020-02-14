<?php
/**
 * 将数组数据输出成execl格式并提供下载
 * 2015-10-26 增加对rowspan支持
 */

/** Error reporting */
//error_reporting ( E_ALL );

/** Include path **/
//ini_set('include_path', ini_get('include_path').';D:/MyProject/WebLib/PHPExecl/');


/** PHPExcel */
//require_once C ( 'PHPExeclPath' ) . 'PHPExcel.php';
require_once  '../../WebLib/PHPExcel/PHPExcel.php';
/** PHPExcel_Writer_Excel2007 */
//include 'PHPExcel/Writer/Excel2007.php';
/**
 * 
 * 根据数据内容输出excel数据，并直接下载
 * @param array $data
 * 
 * $data[title]=array(array(标题数组),array(标题数组),...);	excel可以有多行标题
 * - excel可以有多行标题，每行标题用一个标题数组描述其属性，标题数组字段如下(大小写敏感)：
 * - text：string 标题文字
 * - font：string 标题字体名称
 * - bold：bool 加粗显示
 * - color: ARGB颜色16进制字串。就是RGB颜色值前面加了两字符的透明值，FF为不透明00为全透明。
 *
 * $data[header]=array(array(表头数组),array(表头数组)...);	表头数据
 * - 可以有多行表头，可跨列、跨行。表头数组字段：
 * - text：string 字段文字
 * - name: string 字段名，将与rows数据字段匹配
 * - width: int	宽度(字符数)
 * - colspan: int	占据列数
 * - rowspan: int	占据行数
 * 
 * $data[rows]==array(array(数据数组),array(数据数组)...);	数据
 * -数据数组为name=>value对
 * 
 * $data[footer]结构同数据仅附加到数据末尾。
 * 
 */
function exportExecl($data) {
	ob_clean ();
    ini_set('memory_limit', '2048M');
    $cacheMethod = PHPExcel_CachedObjectStorageFactory::cache_in_memory_serialized;
    //$cacheMethod = PHPExcel_CachedObjectStorageFactory::cache_to_discISAM;
    PHPExcel_Settings::setCacheStorageMethod($cacheMethod);

	$objPHPExcel = new PHPExcel ();
	
	$col = 'A';
	$row = 1; //execl当前行列指针
	

	$objPHPExcel->setActiveSheetIndex ( 0 );
	$sheet = $objPHPExcel->getActiveSheet ();
	
	//处理标题行
	foreach ( $data [title] as $title ) {
		$sheet->setCellValue ( $col . $row, $title [text] );
		if (isset ( $title [size] ))
			$sheet->getStyle ( $col . $row )->getFont ()->setSize ( $title [size] );
		if (isset ( $title [font] ))
			$sheet->getStyle ( $col . $row )->getFont ()->setName ( $title [font] );
		if (isset ( $title [bold] ))
			$sheet->getStyle ( $col . $row )->getFont ()->setBold ( true );
		if (isset ( $title [color] ))
			$sheet->getStyle ( $col . $row )->getFont ()->getColor ()->setARGB ( $title [color] );
		$row ++;
	}
	//处理表头
	$usedCell = array (); //已经被表头内容占用的Cell
	$index = array (); //记录名称到列的索引
	
//dump($data['header']); die('pp');
	//为跨列定义的头影像$headmap[row][col]如果有定义说明此单元已经由于跨行别占用，
	//其值为最接近的可使用列
	$headmap = array ();

	foreach ( $data ['header'] as $header ) { //每循环处理一行表头
		//dump($headmap);
        //$sheet->getStyle($col)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_TEXT);
		foreach ( $header as $field ) { //处理每一标题列
			//有跨行，跳过已经被占用的列----
//echo $row.'-'.$col.'>';
			while ( isset ( $headmap [$row] [$col] ) )
				$col = $headmap [$row] [$col];
			
//dump($headmap); die('ggg');
			$sheet->setCellValue ( $col . $row, $field [text] );
			//标题加粗居中
			$sheet->getStyle ( $col . $row )->getFont ()->setBold ( true );
			$sheet->getStyle ( $col . $row )->getAlignment ()->setHorizontal ( PHPExcel_Style_Alignment::HORIZONTAL_CENTER );
			$sheet->getStyle ( $col . $row )->getAlignment ()->setVertical ( PHPExcel_Style_Alignment::VERTICAL_CENTER );
			if (isset ( $field [width] ))
				$sheet->getColumnDimension ( $col )->setWidth ( $field [width] );
			$nowCol = $col; //在作跨列处理前当前数据列-----
//dump($header);dump($field);

			if (isset ( $field [colspan] )) { //跨列
//echo $field [colspan].'|';
				$col2 = nextCol ( $col, $field [colspan] - 1 );
				//$sheet->mergeCells($col.$row.':'.$col2.$row);
				$col = $col2;
			}
//echo $col.'|';	die('kj');
			if (isset ( $field [rowspan] )) { //跨行
				//设置占用数据
				for($i = $row; $i < $row + $field [rowspan]; $i ++)
					$headmap [$i] [$nowCol] = nextCol ( $col );
			}
			//合并格单元
			if (isset ( $field [colspan] ) || isset ( $field [rowspan] )) {
				$rowspan = (isset ( $field [rowspan] )) ? $field [rowspan] - 1 : 0;
				$sheet->mergeCells ( $nowCol . $row . ':' . $col . ($row + $rowspan) );
			}
			$index [$field [name]] = $col; //建立从字段名获得对应execl列的索引数组
			$col = nextCol ( $col );
//echo $col,'=';
//die('eet');
		}
		$row ++;
		$col = 'A';
//die('aaaaaa');
	}
//dump($index);die('hhh');
	//数据
	foreach ( $data [rows] as $dt ) {
		foreach ( $dt as $name => $val ) {
			if (isset ( $index [$name] )) {
			    if(strlen($val)>11)    //长整数转换为字串格式, 避免身份证等变成科学记数法显示
			        $sheet->setCellValueExplicit($index [$name] . $row, $val, PHPExcel_Cell_DataType::TYPE_STRING);
			    else
                    $sheet->setCellValue($index [$name] . $row, $val);
                //
            }
		}
		$row ++;
		unset($dt,$name,$val);
	}
	//表尾
	//dump($data[footer]);
	foreach ( $data [footer] as $footer ) {
		foreach ( $footer as $name => $val ) {
			if (isset ( $index [$name] ))
				$sheet->setCellValue ( $index [$name] . $row, $val );
		}
		$row ++;
	}
	//die('eee');
    //unset($data);
	$defaultFile = (isset ( $data [defaultFile] )) ? $data [defaultFile] : "myfile.xlsx";
	header ( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
	header ( 'Content-Disposition: attachment;filename="' . $defaultFile . '"' );
	header ( 'Cache-Control: max-age=0' );
	
	$objWriter = PHPExcel_IOFactory::createWriter ( $objPHPExcel, 'Excel2007' );
	$objWriter->save ( 'php://output' );
	
	exit ();
}

function nextCol($col, $step = 1) {
	$result = '';
	$i=strlen($col)-1;
	do{
		$asc=($i>=0)?ord ( $col [$i] ) + $step-ord('A'):$step-1;
		$remainder=$asc%26;
	
		$result =chr($remainder+ord('A')).$result;
		$step=intval($asc/26);
		$i--;
	}while($step!=0);
	if($i>=0) $result=substr($col,0,$i+1).$result;
	return $result;
}

?>