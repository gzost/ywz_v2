/**
 * 响应RecvData消息，根据消息数据中的element对象的相关属性，更新html对象
 *
 */

(function(){
    //console.log("element UPdate init ");
    $(window).on("RecvData",function (event,data) {

        if("object"!= typeof data.element)  return;

        var elm=data.element;
        for(const key in elm){
            //console.log(key+":::::::"+elm[key]);
            $("#"+key).html(elm[key]);
        }
    });
})();