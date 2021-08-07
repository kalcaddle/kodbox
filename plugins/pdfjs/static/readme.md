
## 更新处理;

### PDF.js
当前版本: 2.5.207 2020/06/01
版本更新: https://github.com/mozilla/pdfjs-dist/releases  (更新build内容)
版本更新: https://github.com/mozilla/pdf.js/releases  (更新web内容)

```
pdfjs/web/viewer.js
搜索:	var userOptions = Object.create(null);
添加:	userOptions =  window.pdfOptions || userOptions; //add by warlee;
```

### ofd
数科阅读器: http://47.95.198.10:8091/convert-issuer/title/demoview.do
永中OFD:   https://www.yozodcs.com/page/example.html


### djvu
https://github.com/mateusz-matela/djvu-html5