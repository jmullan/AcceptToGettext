AcceptToGettext
===============

Parses the 'Accept-Language' and 'Accept-Charset' HTTP/1.1 headers

In PHP, you have the possibility to use gettext, which makes translating a message a lot easier.

Of course, translation is only interesting if you provide the user with the language and encoding he asks for. Since there's part of the HTTP protocol that allows you to specify that, it's nice if one can use that information to pick the 'right' translation out of the ones you have available.

I wrote something that does just that -- it parses the 'Accept-Language' and 'Accept-Charset' HTTP/1.1 headers, and tells you which one the user prefers out of a list you provide.

Note that even if the user's browser may support this, the user may not know about it; as such, it's probably good practice not to remove links to other-languaged versions, so that people can still get a version of your site in another language if they so prefer.

I wrote this entirely in PHP (4; not sure whether it works under PHP3 (as in "didn't test it")), so, no C code at all. The code is here. Feedback (e.g., bugreports, enhancements, ...) about this is highly appreciated at wouter@grep.be. 

http://grep.be/articles/php-accept
