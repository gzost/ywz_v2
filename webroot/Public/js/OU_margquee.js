/**
 * 定义跑马灯类，这部分可以独立成一个文件
 * 示例化时必须提供：
 * blk string: 装载走马灯的容器通常是div id
 * option object: 可选的参数
 *
 *  显示项：
 *  {   type:1, //必须，1-文字，2-图片
     *      content:<string>,    //必须，文字内容或图片说明
     *      loop:<number>,   //选项，此项循环显示次数，不提供为无限次
     *      speed:<number>,   //选项，滚动速度5~5000，越大速度越快，默认100.
     *      color:<string>, //文字颜色
     *      backgrand:<string>,   //背景颜色
     *      href:<string>,  //跳转的URL
     *  }
 * 使用：
 *  var marquee=new OU_margquee("blk-announce",{}); //建立实例
 *  marquee.appendItem({type:1,content:"当前节点数据,同一链表中element要么是string/int,要么是object",loop:1});   //添加要显示的项目
 *  var item={type:1,content:"新时代德育工作的创新路径",speed:5000,loop:3};
 *  marquee.appendItem(item);
 *  marquee.show(); //开始显示，开始显示时自动把显示容器变为可见
 */
function OU_margquee(blk,options){

    /* **********
    单向链表类，可以拉出来独立使用
    ********* */

    //存储链表的一个节点
    function Node(element){
        this.element=element;   //当前节点数据,同一链表中element要么是string/int,要么是object
        this.next=null; //下一节点数据
    }

    //链表类
    function LList () {
        this.head = new Node( 'head' );     //头节点
    }
    LList.prototype={
        //查找给定节点
        find:function ( item ) {
            var currNode = this.head;
            var isObj=("object"==typeof(item)); //是否为对象
            while ( (currNode!=null) ){ // && (currNode.element != item)
                if(isObj && (JSON.stringify(currNode.element)==JSON.stringify(item)) || !isObj &&  (currNode.element == item))
                    break;
                currNode = currNode.next;
            }
            return currNode;
        },
        //向某一元素后插入新节点
        insert:function(newElement, item){
            var newNode = new Node( newElement );
            var currNode = this.find( item );
            newNode.next = currNode.next;
            currNode.next = newNode;
        },
        //查找某一节点的前驱
        findPrevious:function (item) {
            var currNode=this.head;
            var isObj=("object"==typeof(item)); //是否为对象
            while((currNode.next!=null)){
                if(isObj && (JSON.stringify(currNode.next.element)==JSON.stringify(item)) || !isObj &&  (currNode.next.element == item))
                    break;
                currNode=currNode.next;
            }
            return currNode;
        },
        //删除节点
        remove:function (item) {
            var prevNode = this.findPrevious( item );
            if( !( prevNode.next == null ) ){
                prevNode.next = prevNode.next.next;
            }
        },
        //修改某一节点的数据
        edit:function (item,newItem) {
            var element=this.find(item);
            element.element=newItem;
        },
        //取头节点
        getHead:function(){
            return this.head;
        },
        //取下一节点,当链表只剩当前节点时，会返回当前节点，外部需做判断
        //当链表为空时返回null
        getNext:function(item){
            if(item.next != null) return item.next;
            else return this.head.next;
        },
        //控制台上显示所有节点
        display:function(){
            var currNode=this.head;
            while (!(currNode.next==null)){
                console.log(currNode.next.element);
                currNode=currNode.next;
            }
        }
    }
    /*
    console.log("====单向链表测试====");
    var names=new LList();
    names.insert({type:1, content:"ooeee111",loop:2},"head");
    names.insert("bbb222","head");
    names.insert({type:1, content:"kkww",loop:3},"head");
    names.display();

    var curr=names.find({type:1, content:"kkww",loop:3});
    console.log("find item=",curr);
    curr.element.loop--;
    var curr2=names.find( curr.element);
    console.log("aft loop-- find=",curr2);
    names.display();
    */
    /////单向链表类结束


    //默认参数
    var defaults={
        font_size:"16px",   //文字大小
        color:"#FFFFFF",    //文字颜色
        background:"#003366",   //背景颜色
        autoHide:true,  //没内容显示自动隐藏显示容器
        speed:100,  //滚动速度1~1000，数字越大滚动越快
        height:"28px",  //滚动条高度
    }
    var params=$.extend(defaults,options);

    var items=new LList();    //存储要显示的对象链表

    //初始化
    var container=$("#"+blk);   //容器JQ对象
    var containerWidth=container.width();  //容器宽度


    //设置容器的固定CSS参数
    container.css({position:'relative',overflow: 'hidden','text-align': 'left'});

    /**
     * 显示下一节点内容，因此第一次调用时需要一个引导节点或叫头节点。节点符合单向链表结构
     * @param currItem  当前节点
     */
    var showNext=function (currItem) {
//console.log("next======");
//items.display();

        var nextItem=items.getNext(currItem);
        if(nextItem==null) {
            //已经没有需要显示的内容了
            if(params.autoHide) container.hide();   //隐藏显示容器
            return;
        }

        //设置文字大小、颜色及背景颜色
        var font_size=("string"==typeof(nextItem.element.font_size))?nextItem.element.font_size:params.font_size;
        var color=("string"==typeof(nextItem.element.color))?nextItem.element.color:params.color;
        var background=("string"==typeof(nextItem.element.background))?nextItem.element.background:params.background;
        var height=nextItem.element.height||defaults.height;
        var speed=parseInt(nextItem.element.speed)||params.speed;
        if( speed<5 || speed>5000 ) speed=params.speed;
//console.log("color=",color,"background=",background);
        container.css({"font-size":font_size,color:color,"background-color":background,"height":height});

        var containerHeight=container.height();  //容器高度
        if(nextItem.element.type==1){
            //组装滚动内容HTML
            var contain=$("<div style='position: relative;display: inline-block;padding-top: 2px;white-space: nowrap;'></div>");
            contain.html(nextItem.element.content);
            container.html(contain);

            //一次计算滚动时间
            var containWidth= contain.width();

            var moveTime=(containWidth+containerWidth)*2000/speed;
            console.log("speed=",speed);


            //处理超链接
            if("string"==typeof(nextItem.element.href) && (nextItem.element.href.length>1) ){
                var link=$("<a  style='color:inherit;'></a>");
                link.attr("href",nextItem.element.href);
                console.log("href=",nextItem.element.href," len=",nextItem.element.href.length);
                contain.wrap(link);
            }
            //启动动画显示
            contain.css("left",(containerWidth-50)+"px");
            contain.animate({left: (0-containWidth)+'px'},moveTime,"linear",function () {
                showNext(nextItem); //一个项目显示完后回调
            });
        }else{
            //组装滚动内容HTML
            var contain=$("<div style='position: relative;display: block;padding-top: 0;white-space: nowrap; text-align: center;'></div>");
            var img=$("<img  style='max-width: 100%;'/>");  //图片最大宽度为容器宽度
            img.attr("src",nextItem.element.imgurl);
            contain.html(img);
            container.html(contain);

            //计算向上滚动的距离及时间，延时一点时间等浏览器渲染好，才能准确获得新增加元素的高度
            setTimeout(function () {
                var containHeight=contain.height();
                var distance=containerHeight-containHeight;   //容器高度与图片高度之差
                contain.animate({top: distance},Math.abs(distance)*speed/2,"linear",function () {
                    //滚动完后停留5秒
                    setTimeout(function () {
                        showNext(nextItem);
                    },5000);
                });
            },100);
        }

        //已显示项定义了循环显示次数则减1，<=0时删除已显示节点
        if(("object"==typeof(nextItem.element)) && ("number"==typeof(nextItem.element.loop) ) ){
            nextItem.element.loop--;
            if(nextItem.element.loop <=0 ) items.remove(nextItem.element)
        }
    }

    this.appendItem=function (item) {
        if("string"==typeof(item.speed) ) item.speed=parseInt(item.speed);
        if("string"==typeof(item.loop) ) item.loop=parseInt(item.loop);
        if("string"==typeof(item.zone) ) item.zone=parseInt(item.zone);
        if("string"==typeof(item.type) ) item.type=parseInt(item.type);
        items.insert(item,"head");
    }

    this.show=function () {
        var currItem=items.getHead();   //取链表头
        if(currItem.next != null){
            //有可显示的项目
            container.show();
            showNext(currItem);
        }
    }
}
