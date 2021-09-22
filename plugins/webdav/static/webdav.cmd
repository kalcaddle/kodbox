:: webdav适配调整: 允许http,文件大小限制50m变更为4G)
:: reg help: https://www.cnblogs.com/byron0918/p/4832556.html; 
:: change https://tool.oschina.net/hexconvert/;

@echo off
cls
echo 
echo change webdav(allow http,fileSizeLimit 50M to 4G)

net stop webclient
reg add HKEY_LOCAL_MACHINE\SYSTEM\CurrentControlSet\Services\WebClient\Parameters /f /v BasicAuthLevel /t reg_dword /d 2 
reg add HKEY_LOCAL_MACHINE\SYSTEM\CurrentControlSet\Services\WebClient\Parameters /f /v FileSizeLimitInBytes /t reg_dword /d 4294967295
net start webclient
sc config webclient start=auto

mshta vbscript:msgbox("webdav apply success")(window.close)