function news(b){if($("#newsletter").prop("checked")){if(""==$("#firstname").val())return alert("Please enter your First Name"),!1;if(""==$("#lastname").val())return alert("Please enter your Last Name"),!1;var a=0;$("#newsletter").prop("checked")&&(a=1);b.href=b.href+"&_newsletter="+a+"&_firstname="+escape($("#firstname").val())+"&_lastname="+escape($("#lastname").val())}$("#devbranch").prop("checked")?b.href+="&_branch=dev":$("#stablebranch").prop("checked")&&(b.href+="&_branch=stable");return!0}
function pm_get_credits(){rcmail.http_post("plugin.plugin_manager_getcredits","")}function pm_discard(){$(".costs").prop("checked",!1);$("#cdlprice").text(pm_price());$("#cdlremaining").text(parseInt($("#cdlcredits").text())-parseInt($("#cdlprice").text()))}function pm_notinstalled(){$(".notinstalled").prop("checked",!1);$("#cdlprice").text(pm_price());$("#cdlremaining").text(parseInt($("#cdlcredits").text())-parseInt($("#cdlprice").text()))}
function pm_update_credits(b){if(b&&""!=$("#cdlcredits").text()&&parseInt(b)!=parseInt($("#cdlcredits").text())){var a=parseInt(b)-parseInt($("#cdlcredits").text());$("#cdlcredits").text(b);$("#cdlprice").text(pm_price());$("#cdlremaining").text(parseInt($("#cdlcredits").text())-parseInt($("#cdlprice").text()));rcmail.display_message("MyRC$ "+a+" "+rcmail.gettext("creditsupdated","plugin_manager"),"confirmation")}window.setTimeout("pm_get_credits()",3E4)}
function pm_hmail(b){$(".chbox").each(function(){$(this).attr("id")&&-1<$(this).attr("id").indexOf("_hmail_")&&!$(this).prop("disabled")&&($(this).prop("checked",b),$(this).trigger("click"),$("#cdlprice").text(pm_price()),$("#cdlremaining").text(parseInt($("#cdlcredits").text())-parseInt($("#cdlprice").text())))});var a=[];a.name="pmhmail";a.value=b?0:1;rcmail.save_pref(a)}
function pm_price(){var b=0,a;$(".chbox").each(function(){if($(this).prop("checked")&&(a=$(this).attr("id")))a=a.replace("chbox_","pmdlp_"),$("#"+a).text()&&(b+=parseInt($("#"+a).text()))});return b}function pm_uncheck(){$(".chbox").each(function(){$(this).prop("checked",!1)})}
function pmf(){var b=!1;$(".chbox").each(function(){$(this).prop("checked")&&(b=!0)});if(!b)return rcmail.display_message(rcmail.gettext("noupdates","plugin_manager"),"notice"),!1;var a="";$("input[type=hidden]").each(function(){"_token"!=$(this).prop("name")&&("hosted_button_id"!=$(this).prop("name")&&"cmd"!=$(this).prop("name"))&&(a=a+$(this).prop("name")+": "+$(this).val()+"\n")});if(""!=a){var d=pm_price();if(d>parseInt($("#cdlcredits").text()))return confirm("The price for this download exeeds your MyRC$ credits!\n\nPlease buy credits or discard incurring costs downloads.\n\nYes, buy MyRC$ credits [ok].\nNo, discard downloads [cancel].")?
window.open("./?_task=settings&_action=plugin.plugin_manager_buycredits"):pm_discard(),!1;$("#pm_price").val(d);var a=a.replace("##placeholder##",d),c="",c=(0<d?"Balance\n---------\nWe will charge your account by MyRC$ "+d+" for this download.\n---------\n":"This download is free.\n---------\n\n")+"The form you are about to submit to...\n"+$("form").prop("action")+"\n...contains hidden fields:\n\n"+a+"\n",c=c+"Fairness is our mission. Be sure we will treat your informations as confidential.\n\n",
c=c+"NOTE: To guarantee the origin of this message, the text is hard coded (no localization possible).\n\n",c=c+"Do you agree?";if(d=confirm(c))$("#toggle").prop("checked",!1),window.setTimeout("pm_uncheck();",500),$("#cdlcredits").text($("#cdlremaining").text()),$("#cdlprice").text(0);return d}return!0}function pm_resize(){if($("#table-container").get(0)){var b=$("#table-container").offset().top,a=$("#update_footer").height(),b=$(window).height()-b-a-70;$("#table-container").height(b)}}
function pm_goToByScroll(b){$(".qtip").hide();$("#table-container").animate({scrollTop:$("#"+b).offset().top-$("#table").offset().top},"slow")}$(window).resize(function(){pm_resize()});
$(document).ready(function(){"undefined"!=typeof rcmail.get_cookie&&"larry"==rcmail.env.skin&&(1==rcmail.get_cookie("minimalmode")&&($("#mainscreen").css("top","65px"),$("#paypal").css("top","50px")),$(window).resize(function(){1==rcmail.get_cookie("minimalmode")?($("#mainscreen").css("top","65px"),$("#paypal").css("top","50px")):($("#mainscreen").css("top","132px"),$("#paypal").css("top","100px"))}));$(".about-link").click(function(){$(".ui-tooltip").hide()});rcmail.addEventListener("init",function(){rcmail.addEventListener("plugin.plugin_manager_getcredits",
pm_update_credits)});var b=$("#paypalcontainer").html();$("#paypalcontainer").remove();$("body").append(b);$("#message").hide();$("#newsletter").click(function(){$("#newletterdetails").toggle();$("#firstname").focus()});$("#updatetoggle").click(function(){$(this).prop("checked")?($(this).attr("title",rcmail.gettext("showall","plugin_manager")),$("td").each(function(){($(this).hasClass("ok")||$(this).hasClass("thirdparty"))&&$(this).parent().hide()})):($(this).attr("title",rcmail.gettext("hideuptodate",
"plugin_manager")),$("td").each(function(){($(this).hasClass("ok")||$(this).hasClass("thirdparty"))&&$(this).parent().show()}))});pm_resize();window.setTimeout("pm_get_credits()",3E4);$(".chbox").click(function(){$("#cdlprice").text(pm_price());$("#cdlremaining").text(parseInt($("#cdlcredits").text())-parseInt($("#cdlprice").text()))});rcmail.env.google_ads?window.setTimeout("$('#table').width($('#prefs-box').width() - 290);",100):window.setTimeout("$('#table').width($('#prefs-box').width() - 50)",
100);$(".anchorLink").live("click",function(){id="pmu_"+$(this).attr("href").substr(1);pm_goToByScroll(id)});$("#toggle").click(function(){var a=$(this).prop("checked");$(".chbox").each(function(){!$(this).prop("disabled")&&$(this).is(":visible")&&$(this).prop("checked",a)});$("#cdlprice").text(pm_price());$("#cdlremaining").text(parseInt($("#cdlcredits").text())-parseInt($("#cdlprice").text()))});$("#table-container").scroll(function(){$(".qtip").hide()});$("th").mouseover(function(){$(".qtip").hide()});
$("#update_footer").mouseover(function(){$(".qtip").hide()});$("td").each(function(){if($(this).attr("title")){var a={},a=$(this).attr("title").split(" :: "),a=2==a.length?{title:a[0],text:a[1]}:{text:$(this).attr("title")},b="left bottom";$(this).attr("id")&&-1<$(this).attr("id").indexOf("pmu_")&&(b="bottom center");$(this).qtip({content:a,position:{my:"top left",at:b,target:$(this),viewport:$(window)},show:{solo:!0},hide:"click",style:{classes:"ui-tooltip-light"}})}});$(".chbox").each(function(){if($(this).attr("title")){var a=
{},a=$(this).attr("title").split(" :: "),a=2==a.length?{title:a[0],text:a[1]}:{text:$(this).attr("title")};$(this).qtip({content:a,position:{my:"top left",at:"left bottom",target:$(this),viewport:$(window)},show:{solo:!0},style:{classes:"ui-tooltip-red"}})}})});