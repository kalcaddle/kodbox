:: webdav适配调整: 允许http,文件大小限制50m变更为2G)
:: reg help: https://www.cnblogs.com/byron0918/p/4832556.html; 
:: change https://tool.oschina.net/hexconvert/;

@echo off
cls
echo 
echo change webdav(allow http,fileSizeLimit 50M to 10G)

net stop webclient
reg add HKEY_LOCAL_MACHINE\SYSTEM\CurrentControlSet\Services\WebClient\Parameters /f /v BasicAuthLevel /t reg_dword /d 2 
reg add HKEY_LOCAL_MACHINE\SYSTEM\CurrentControlSet\Services\WebClient\Parameters /f /v FileSizeLimitInBytes /t reg_dword /d 10737418240
net start webclient

mshta vbscript:msgbox("webdav apply success")(window.close)