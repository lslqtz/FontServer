# FontServer

上一代字体服务器.
1. 通过与站点字幕区集成或单独登录来认证;
2. 支持搜索或下载已录入字体;
3. 可为字幕匹配所需字体并可进行下载 (单个/打包);
4. 自动进行子集化字幕字体处理 (嵌入/非嵌入);

支持的字幕类型: ASS/SSA (包括 ZIP 压缩包内).  
支持的字体类型: TTF (原生), TTC/OTF (转换).

认证功能:  
可采用注册登录 和/或 与站点集成, 其中注册登录机制支持公共登录 (匿名).

核心功能:  
可通过关键词搜索字体, 并根据最大搜索结果数量返回结果.  
在注册登录机制下, 用户可通过手动粘贴 ASS/SSA 字幕内容来处理字幕 (由于只能粘贴文本, 暂不支持压缩包). 在站点集成机制下, 站点可通过约定密钥实现跨站点登录, 或通过约定密钥传递 ASS/SSA/ZIP 字幕文件来处理字幕.  
对字幕进行一定的处理后, 最多可选择下载 单个字体/打包字体/子集化字幕 (嵌入字体)/子集化字幕 (非嵌入字体).

源策略功能:  
源策略可用于对功能进行配置或限制.  
在认证功能中, 可配置 跨站点登录所需约定密钥/限制登录或注册可用性及注册是否需要审批/配置注册确认邮件过期时间/配置是否允许公共登录及公共登录所用 UID.  
在核心功能中, 可配置 允许下载字体/允许下载打包字体/允许下载嵌入字体的子集化字幕/允许下载非嵌入字体的子集化字幕/是否针对每个字幕子集化 (可针对压缩包所有字幕的并集子集化)/缓存字体数量 (内存换性能)/最大字幕文件大小/最小搜索长度/最大搜索结果数量.

未完成 (可能因为懒也不会完成) 的计划:  
允许用户通过上传字体来赚取贡献值完善字体库, 并通过用户的贡献值来调整源策略.

通过 Scraper, 可以将字体信息抓取至数据库 (可能有内存泄漏). 字体服务器支持配置多个字体路径, 从前至后尝试, 因此字体可同时存储在多个路径或磁盘.

Nginx config:
```
server {
	listen 80;
	server_name font.acgvideo.cn;
	root /var/www/html/font;
	index index.php index.htm index.html;

	#client_max_body_size 30M;

	location /font/ {
		internal;
		max_ranges 0;
		add_header 'Accept-Ranges' 'none';
	}
	location ~ \.php$ {
		sendfile off;
		add_header Cache-Control 'no-store, no-cache, must-revalidate, proxy-revalidate, max-age=0';
		fastcgi_pass unix:/run/php-fpm/www.sock;
		fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
		fastcgi_param PHP_VALUE "post_max_size=30M\nupload_max_filesize=30M";
		include fastcgi_params;
	}
}
```
