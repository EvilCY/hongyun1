// Vue.prototype.vant=vant/////vant 挂载到vue 上
var win = window;
var doc = win.document;
var psdWidth = 750;
var tid;
var throttleTime = 100;
var metaEl = doc.querySelector('meta[name="viewport"]');
if (!metaEl) {
    metaEl = doc.createElement('meta');
    metaEl.setAttribute('name', 'viewport');
    doc.head.appendChild(metaEl);
}
metaEl.setAttribute('content', 'width=device-width,user-scalable=no,initial-scale=1,maximum-scale=1,minimum-scale=1');

var resizeRem = function () {
    doc.documentElement.style.fontSize = window.innerWidth / psdWidth * 100 + 'px';

};

win.addEventListener('resize', function () {
    clearTimeout(tid);
    tid = setTimeout(resizeRem, throttleTime);
}, false);
win.addEventListener('pageshow', function (e) {
    if (e.persisted) {
        clearTimeout(tid);
        tid = setTimeout(resizeRem, throttleTime);
    }
}, false);

resizeRem();
if (doc.readyState === 'complete') {
    resizeRem();
} else {
    doc.addEventListener('DOMContentLoaded', function (e) {
        resizeRem();
    }, false);
}
(function(){
    var _LoadingHtml ='<style>#loadingDiv{position:fixed;left:0;width:100vw;height:100vh;top:0;background:white;opacity:1;filter:alpha(opacity=80);z-index:10000;overflow:hidden;}#loadingDiv .item-loader-container{position:fixed;top:30%;left:0;right:0}#loadingDiv .la-ball-triangle-path,.la-ball-triangle-path>div{position:relative;-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box}#loadingDiv .la-ball-triangle-path{display:block;font-size:0;color:#FFAB29;margin:0 auto}#loadingDiv .la-ball-triangle-path>div{display:inline-block;float:none;background-color:currentColor;border:0 solid currentColor}#loadingDiv .la-ball-triangle-path{width:32px;height:32px}#loadingDiv .la-ball-triangle-path>div{position:absolute;top:0;left:0;width:10px;height:10px;border-radius:100%}#loadingDiv .la-ball-triangle-path>div:nth-child(1){-webkit-animation:ball-triangle-path-ball-one 2s 0s ease-in-out infinite;-moz-animation:ball-triangle-path-ball-one 2s 0s ease-in-out infinite;-o-animation:ball-triangle-path-ball-one 2s 0s ease-in-out infinite;animation:ball-triangle-path-ball-one 2s 0s ease-in-out infinite}#loadingDiv .la-ball-triangle-path>div:nth-child(2){-webkit-animation:ball-triangle-path-ball-two 2s 0s ease-in-out infinite;-moz-animation:ball-triangle-path-ball-two 2s 0s ease-in-out infinite;-o-animation:ball-triangle-path-ball-two 2s 0s ease-in-out infinite;animation:ball-triangle-path-ball-two 2s 0s ease-in-out infinite}#loadingDiv .la-ball-triangle-path>div:nth-child(3){-webkit-animation:ball-triangle-path-ball-tree 2s 0s ease-in-out infinite;-moz-animation:ball-triangle-path-ball-tree 2s 0s ease-in-out infinite;-o-animation:ball-triangle-path-ball-tree 2s 0s ease-in-out infinite;animation:ball-triangle-path-ball-tree 2s 0s ease-in-out infinite}#loadingDiv .la-ball-triangle-path.la-sm{width:16px;height:16px}#loadingDiv .la-ball-triangle-path.la-sm>div{width:4px;height:4px}#loadingDiv .la-ball-triangle-path.la-2x{width:64px;height:64px}#loadingDiv .la-ball-triangle-path.la-2x>div{width:20px;height:20px}#loadingDiv .la-ball-triangle-path.la-3x{width:96px;height:96px}#loadingDiv .la-ball-triangle-path.la-3x>div{width:30px;height:30px}@-webkit-keyframes ball-triangle-path-ball-one{0%{-webkit-transform:translate(0,220%);transform:translate(0,220%)}17%{opacity:.25}33%{opacity:1;-webkit-transform:translate(110%,0);transform:translate(110%,0)}50%{opacity:.25}66%{opacity:1;-webkit-transform:translate(220%,220%);transform:translate(220%,220%)}83%{opacity:.25}100%{opacity:1;-webkit-transform:translate(0,220%);transform:translate(0,220%)}}@-moz-keyframes ball-triangle-path-ball-one{0%{-moz-transform:translate(0,220%);transform:translate(0,220%)}17%{opacity:.25}33%{opacity:1;-moz-transform:translate(110%,0);transform:translate(110%,0)}50%{opacity:.25}66%{opacity:1;-moz-transform:translate(220%,220%);transform:translate(220%,220%)}83%{opacity:.25}100%{opacity:1;-moz-transform:translate(0,220%);transform:translate(0,220%)}}@-o-keyframes ball-triangle-path-ball-one{0%{-o-transform:translate(0,220%);transform:translate(0,220%)}17%{opacity:.25}33%{opacity:1;-o-transform:translate(110%,0);transform:translate(110%,0)}50%{opacity:.25}66%{opacity:1;-o-transform:translate(220%,220%);transform:translate(220%,220%)}83%{opacity:.25}100%{opacity:1;-o-transform:translate(0,220%);transform:translate(0,220%)}}@keyframes ball-triangle-path-ball-one{0%{-webkit-transform:translate(0,220%);-moz-transform:translate(0,220%);-o-transform:translate(0,220%);transform:translate(0,220%)}17%{opacity:.25}33%{opacity:1;-webkit-transform:translate(110%,0);-moz-transform:translate(110%,0);-o-transform:translate(110%,0);transform:translate(110%,0)}50%{opacity:.25}66%{opacity:1;-webkit-transform:translate(220%,220%);-moz-transform:translate(220%,220%);-o-transform:translate(220%,220%);transform:translate(220%,220%)}83%{opacity:.25}100%{opacity:1;-webkit-transform:translate(0,220%);-moz-transform:translate(0,220%);-o-transform:translate(0,220%);transform:translate(0,220%)}}@-webkit-keyframes ball-triangle-path-ball-two{0%{-webkit-transform:translate(110%,0);transform:translate(110%,0)}17%{opacity:.25}33%{opacity:1;-webkit-transform:translate(220%,220%);transform:translate(220%,220%)}50%{opacity:.25}66%{opacity:1;-webkit-transform:translate(0,220%);transform:translate(0,220%)}83%{opacity:.25}100%{opacity:1;-webkit-transform:translate(110%,0);transform:translate(110%,0)}}@-moz-keyframes ball-triangle-path-ball-two{0%{-moz-transform:translate(110%,0);transform:translate(110%,0)}17%{opacity:.25}33%{opacity:1;-moz-transform:translate(220%,220%);transform:translate(220%,220%)}50%{opacity:.25}66%{opacity:1;-moz-transform:translate(0,220%);transform:translate(0,220%)}83%{opacity:.25}100%{opacity:1;-moz-transform:translate(110%,0);transform:translate(110%,0)}}@-o-keyframes ball-triangle-path-ball-two{0%{-o-transform:translate(110%,0);transform:translate(110%,0)}17%{opacity:.25}33%{opacity:1;-o-transform:translate(220%,220%);transform:translate(220%,220%)}50%{opacity:.25}66%{opacity:1;-o-transform:translate(0,220%);transform:translate(0,220%)}83%{opacity:.25}100%{opacity:1;-o-transform:translate(110%,0);transform:translate(110%,0)}}@keyframes ball-triangle-path-ball-two{0%{-webkit-transform:translate(110%,0);-moz-transform:translate(110%,0);-o-transform:translate(110%,0);transform:translate(110%,0)}17%{opacity:.25}33%{opacity:1;-webkit-transform:translate(220%,220%);-moz-transform:translate(220%,220%);-o-transform:translate(220%,220%);transform:translate(220%,220%)}50%{opacity:.25}66%{opacity:1;-webkit-transform:translate(0,220%);-moz-transform:translate(0,220%);-o-transform:translate(0,220%);transform:translate(0,220%)}83%{opacity:.25}100%{opacity:1;-webkit-transform:translate(110%,0);-moz-transform:translate(110%,0);-o-transform:translate(110%,0);transform:translate(110%,0)}}@-webkit-keyframes ball-triangle-path-ball-tree{0%{-webkit-transform:translate(220%,220%);transform:translate(220%,220%)}17%{opacity:.25}33%{opacity:1;-webkit-transform:translate(0,220%);transform:translate(0,220%)}50%{opacity:.25}66%{opacity:1;-webkit-transform:translate(110%,0);transform:translate(110%,0)}83%{opacity:.25}100%{opacity:1;-webkit-transform:translate(220%,220%);transform:translate(220%,220%)}}@-moz-keyframes ball-triangle-path-ball-tree{0%{-moz-transform:translate(220%,220%);transform:translate(220%,220%)}17%{opacity:.25}33%{opacity:1;-moz-transform:translate(0,220%);transform:translate(0,220%)}50%{opacity:.25}66%{opacity:1;-moz-transform:translate(110%,0);transform:translate(110%,0)}83%{opacity:.25}100%{opacity:1;-moz-transform:translate(220%,220%);transform:translate(220%,220%)}}@-o-keyframes ball-triangle-path-ball-tree{0%{-o-transform:translate(220%,220%);transform:translate(220%,220%)}17%{opacity:.25}33%{opacity:1;-o-transform:translate(0,220%);transform:translate(0,220%)}50%{opacity:.25}66%{opacity:1;-o-transform:translate(110%,0);transform:translate(110%,0)}83%{opacity:.25}100%{opacity:1;-o-transform:translate(220%,220%);transform:translate(220%,220%)}}@keyframes ball-triangle-path-ball-tree{0%{-webkit-transform:translate(220%,220%);-moz-transform:translate(220%,220%);-o-transform:translate(220%,220%);transform:translate(220%,220%)}17%{opacity:.25}33%{opacity:1;-webkit-transform:translate(0,220%);-moz-transform:translate(0,220%);-o-transform:translate(0,220%);transform:translate(0,220%)}50%{opacity:.25}66%{opacity:1;-webkit-transform:translate(110%,0);-moz-transform:translate(110%,0);-o-transform:translate(110%,0);transform:translate(110%,0)}83%{opacity:.25}100%{opacity:1;-webkit-transform:translate(220%,220%);-moz-transform:translate(220%,220%);-o-transform:translate(220%,220%);transform:translate(220%,220%)}}</style><div id="loadingDiv"><div class="item-loader-container"><div class="la-ball-triangle-path la-2x"><div></div><div></div><div></div></div></div></div>'
    //呈现loading效果
    document.write(_LoadingHtml);
    //监听加载状态改变
    document.onreadystatechange = completeLoading;

    //加载状态为complete时移除loading效果
    function completeLoading() {
        if (document.readyState == "complete") {
            setTimeout(function(){
                var loadingMask = document.getElementById('loadingDiv');
                loadingMask.parentNode.removeChild(loadingMask);
            },1000);

        }
    }
})();
