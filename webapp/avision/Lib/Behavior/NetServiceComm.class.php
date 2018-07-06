<?php
/**
 * 外部功能扩展：通信网络通信与服务交换信息
 * @brief 与本地或异地服务进行TCP通信，以字串Jason格式作为通信格式，具体相应的指令见定义
 */
class NetServiceComm extends Behavior {
    // 行为参数定义
	// 设置通信参数：ip、port、timeout
    protected $options   =  array(
        //'SHOW_FUN_TIMES'    => false ,  // 显示函数调用次数
    );

	/**
		@brief 行为扩展的执行入口必须是run
		@param content 指令（Jason格式）
	*/

    public function run(&$content){

		//与服务进行TCP连接

		if(true)
		{
			//连接成功

			//发送指令
			if(true)
			{
				//发送成功
			}
			else
			{
				//发送失败
			}

			//等待接收返回结果
		}
		else
		{
			//连接失败
		}


		//返回结果
    }


}