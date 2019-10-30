/**
 * 扩展jquery插件
 * 依赖：jquery
 * 使用方法：
 * <div id="contina" data-options={options}></div>
 * $("#contina").rate({options});
 * 其中：options的传入值，可覆盖settings的参数
 */
;(function($){
    $.fn.rate = function(options){
        var defaults = {
            //各种参数，各种属性
            count: 6,   //star总数
            score: 3,   //star实心数
            color: "orange",//star颜色
            size: "18px",//star大小
            star:"★",   //实心星星
            star_o:"☆",  //空心星星
            editable:true,  //是否允许编辑
            //鼠标点击选中时调用, score:选择的值,target:触发事件的jq对象，返回布尔值赋给editable.
            onSelect:function (score,target) {
                return true;
            }
        }

        var options = $.extend(defaults,options);
//console.log(options);
        this.each(function(){
            //各种功能  //可以理解成功能代码
            var _this = $(this);
            var dataOptionStr=_this.attr('data-options');
            if(typeof(dataOptionStr)=='undefined') dataOptionStr='{}';
            var dataOptions=eval('('+dataOptionStr+')')
//console.log(dataOptionStr);
//console.log(dataOptions)
            var localOptions={};
            localOptions=$.extend(localOptions,options);
            localOptions=$.extend(localOptions,dataOptions);
//console.log(localOptions)
            for (let i = 0; i < localOptions.count; i++) {
                var star = $('<span class="star" ></span>');
                star.css({"color":localOptions.color, "font-size":localOptions.size});
                if (i < localOptions.score) {
                    star.text(localOptions.star)
                } else {
                    star.text(localOptions.star_o)
                }
                _this.append(star)
            }
            //$(settings.container+ " .star").css('cursor','pointer');
            //在当前调用的id里面
            _this.find(".star").mouseenter(function () {
                setStar(_this,$(this).index() + 1,localOptions)
            })
            _this.find(".star").mouseleave(function () {
                setStar(_this,localOptions.score,localOptions)
            })
            _this.find(".star").click(function () {
                if(localOptions.editable){
                    localOptions.score = $(this).index() + 1
                    localOptions.editable=localOptions.onSelect(localOptions.score,_this)
                }
            })

        });
        function setStar(container,val,options) {
            //console.log(val);
            if(options.editable){
                container.find(".star").each(function (i) {
                    if (i < val) {
                        $(this).text(options.star);
                    } else {
                        $(this).text(options.star_o);
                    }
                })
            }
        }
        return this;
    }
})(jQuery);