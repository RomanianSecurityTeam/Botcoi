About
===
<strong>Botcoi</strong> is an interactive bot written for <a href="https://rstforums.com/chat/" target="_blank">RST Chat</a>. It responds to the following commands:

```
bc joke
	Returns a random joke from a few APIs around the Web

bc compute|comp <expression>
	Returns the output of WolframAlpha's API. <expression>
	can be a complex equation; a world event, statistic or much more;
	something that you consider it could be stored somewhere as important data.
	i.e. bc comp fastest man

bc realurl <short_url>
	Returns the final URL of <short_url>. It can be used on bit.ly or goo.gl
	links, for example.

bc header <header_name> <website_url>
	Returns the value of <header_name> from the <website_url> HTTP request.
	i.e. bc header content-type http://google.com

bc convert|conv <amount> <a> <b>
	Converts <amount> from <a> to <b>. <a> and <b> must be a 3-letter
	abbreviation, you can find them here: http://goo.gl/AxL3WZ
	i.e. bc conv 100 eur ron

bc ip <website_url>
	Returns the IP address of <website_url>

bc b64|base64 e|encode|d|decode <string>
	Encodes/decodes <string> using Base64
	i.e. bc b64 e Botcoi

bc r13|rot13 <string>
	Applies Rot13 shift cipher to <string>

bc md5 <string>
	Computes the MD5 hash of <string>

bc sha1 <string>
	Computes the SHA1 hash of <string>

bc url e|encode|d|decode <string>
	Encodes/decodes <string> to make it URL valid
	i.e. bc url e 1&1
```

Author: <a href="http://webtoil.co/" target="_blank">Gecko - webtoil.co</a>
