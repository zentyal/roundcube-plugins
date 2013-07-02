/*
 http://www.gnu.org/licenses/gpl.html [GNU General Public License]
 @param {jQuery} {base64Encode:function(input))
 @param {jQuery} {base64Decode:function(input))
 @return string
*/
(function(j){j.extend({base64Encode:function(b){var d="",g,c,f,a,e,h,i=0,b=b.replace(/\x0d\x0a/g,"\n");c="";for(f=0;f<b.length;f++)a=b.charCodeAt(f),128>a?c+=String.fromCharCode(a):(127<a&&2048>a?c+=String.fromCharCode(a>>6|192):(c+=String.fromCharCode(a>>12|224),c+=String.fromCharCode(a>>6&63|128)),c+=String.fromCharCode(a&63|128));for(b=c;i<b.length;)g=b.charCodeAt(i++),c=b.charCodeAt(i++),f=b.charCodeAt(i++),a=g>>2,g=(g&3)<<4|c>>4,e=(c&15)<<2|f>>6,h=f&63,isNaN(c)?e=h=64:isNaN(f)&&(h=64),d=d+"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=".charAt(a)+
"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=".charAt(g)+"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=".charAt(e)+"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=".charAt(h);return d},base64Decode:function(b){for(var d="",g,c,f,a,e,h=0,b=b.replace(/[^A-Za-z0-9\+\/\=]/g,"");h<b.length;)g="ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=".indexOf(b.charAt(h++)),c="ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=".indexOf(b.charAt(h++)),
a="ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=".indexOf(b.charAt(h++)),e="ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=".indexOf(b.charAt(h++)),g=g<<2|c>>4,c=(c&15)<<4|a>>2,f=(a&3)<<6|e,d+=String.fromCharCode(g),64!=a&&(d+=String.fromCharCode(c)),64!=e&&(d+=String.fromCharCode(f));b=d;d="";for(e=c1=c2=a=0;a<b.length;)e=b.charCodeAt(a),128>e?(d+=String.fromCharCode(e),a++):191<e&&224>e?(c2=b.charCodeAt(a+1),d+=String.fromCharCode((e&31)<<6|c2&63),a+=2):(c2=
b.charCodeAt(a+1),c3=b.charCodeAt(a+2),d+=String.fromCharCode((e&15)<<12|(c2&63)<<6|c3&63),a+=3);return d}})})(jQuery);