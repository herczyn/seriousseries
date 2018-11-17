var max_results_const = 10;
var max_results = max_results_const;
var ctrl_key_pressed = false;
var form_status = 0;
var column_selected = false;
var start;
var last_tooltip=false;
var ttTimeout;
var last_window_width;

function popovers() {
	brand_new_shows = "";
	for (service_id=0;service_id<servicesArray.length;service_id++) {
		service_info=servicesArray[service_id];
		brand_new_shows += ", <a href='/multi_n_"+service_info[0]+"'>"+service_info[1]+"</a>";
	};
	new_shows_today = "";
	for (service_id=0;service_id<servicesArray.length;service_id++) {
		service_info=servicesArray[service_id];
		new_shows_today += ", <a href='/multi_t_"+service_info[0]+"'>"+service_info[1]+"</a>";
	};
	//$("#brand_new").html("(<a href='/update'>set as viewed</a>"+brand_new_shows+")");
	$("#brand_new").html("(all: "+brand_new_shows.substring(2)+", only today: "+new_shows_today.substring(2)+")");
	if (user_id)
		additional_info = "<br /><br />new shows: "+brand_new_shows.substring(2)+"<br />only today: "+new_shows_today.substring(2);
	else
		additional_info = "";
	for (i=0;i<showArray.length;i++) {
		show_info = showArray[i]; popup_text="";
		if (show_info != undefined) {
			if (!show_info[2])
				popup_text+=show_info[7]+", ";
			popup_text+= "<a target='_blank' href='//www.tvmaze.com/shows/"+show_info[1]+"/d'>info</a>";
			if (show_info[2] && user_id) {
				popup_text += ", <a href='/update/"+show_info[6]+"/'"+(show_info[2]==1 ? "" : " onClick='return confirm(\"Are you sure you want to set all "+show_info[2]+" episodes as watched?\")'")+">viewed</a>";
				if (show_info[3]!='DONT') {
					if (show_info[2]==1) {
						popup_text += "<br />";
						for (service_id=0;service_id<servicesArray.length;service_id++) {
							service_info=servicesArray[service_id];
							if (service_id>0) popup_text +=", "
							address = service_info[2].replace('$name$', show_info[3]).replace('$name_$', show_info[3].replace(' ', '_')).replace('$season$', show_info[4]).replace('$season0$', ("0"+show_info[4]).slice(-2)).replace('$episode$', show_info[5]).replace('$episode0$', ("0"+show_info[5]).slice(-2));
							popup_text += "<a target='_blank' href=\""+address+"\">"+service_info[1]+"</a>";
						};
					} else {
						popup_text += "<br />multilink: ";//unwatched episodes: "+show_info[2]+"<br />";
						for (service_id=0;service_id<servicesArray.length;service_id++) {
							service_info=servicesArray[service_id];
							if (service_id>0) popup_text +=", "
							popup_text += "<a href=\"/multi_s_"+service_info[0]+"/"+show_info[6]+"\">"+service_info[1]+"</a>";
						};
					}
				}
			}
			$('#showL'+show_info[0]).popover({delay:{show:100,hide:10000},trigger:'hover',container:'body',placement:'auto right',html:true,content: popup_text}).on('shown.bs.popover', function () {onOpenTooltip(true)});
			$('#showW'+show_info[0]+' td div').popover({delay:{show:100,hide:10000},trigger:'hover',container:'body',placement:'auto bottom',html:true,content: popup_text+additional_info}).on('shown.bs.popover', function () {onOpenTooltip(true)});
		}
	};
}

function onOpenTooltip(n) {
	if (last_tooltip && (!n || $('.popover').length>1)) last_tooltip.remove();
	clearTimeout(ttTimeout);
	last_tooltip=$('.popover');
	if (last_tooltip) last_tooltip.mouseleave(function() {
		ttTimeout = setTimeout("onOpenTooltip()", 1000);
		last_tooltip.mouseenter(function() {
			clearTimeout(ttTimeout);
		});
	});
}

$(function() {
	$('#more').click(function() {
		max_results+=max_results_const;
		loadResults();
	}).css('cursor', 'pointer');
	
	updateStriping();
	$('.pulsate').hide('pulsate');
	$('.showList').hide();

	$('.dropdown').mouseover(function (){
		$('ul.dropdown-menu').css('top', $(this).position().top + $(this).outerHeight() - 10 + 'px');
		$('ul.dropdown-menu').css('left', $(this).position().left + "px");
	});

	$('.tt').popover({delay:{show:100,hide:10000},trigger:'hover',container:'body',placement:'auto bottom',html:true}).on('shown.bs.popover', function () {onOpenTooltip()});
	$('.po').popover({delay:{show:100,hide:100},trigger:'hover',container:'body',placement:'bottom',html:true});

	$(window).resize(function() {if(last_window_width!=window.innerWidth)showHideCR();});

	$('#search').keyup(function () {
		max_results=max_results_const;
		loadResults();
	});
	$('input[type=checkbox]').click(function() {
		c = this.checked;
		if (this.className) {
			$('input[type=checkbox].' + this.className).each(function(){
				this.checked = c;
			});
		}
	});
	$(window).keydown(function(e) {
		if (e.which == 17)
			ctrl_key_pressed = true;
	});
	$(window).keyup(function(e) {
		if (e.which == 17)
			ctrl_key_pressed = false;
	});
	$('form').submit(function() {
		if (form_status==1 || ctrl_key_pressed)
			this.action = document.location.href;
		else if (form_status==0) {
			form_status = 1;
			var $this = $(this);
			setTimeout(function() {form_status=2;$this.submit()}, 500);
			return false;
		}
	});
	$('thead td').each(function() {
		$(this).mouseover(function() {
			if (column_selected) return;
			$(".highlighted").removeClass("highlighted");
			var col=$(this).index();
			if (col==0) return;
			while (!$(this).hasClass("col"+col) && col>0) col--;
			$("tbody td.col"+col).each(function() {
				if ($(this).text()!="\xa0") {
					$(this).parent().addClass("highlighted");
				}
				$(this).addClass("highlighted");
			});
			$(this).addClass("highlighted");
		});
		$(this).mouseout(function() {
			if (column_selected) return;
			$(".highlighted").each(function() {
				$(this).removeClass("highlighted");
			});
		});
		$(this).click(function() {
			amSelected = column_selected && $(this).hasClass("highlighted");
			column_selected = false;
			$(this).mouseout();
			if (!amSelected) {
				$(this).mouseover();
				column_selected = true;
			}
		});
	});

	$('.glyphicon-remove').prop('title', 'canceled');

	checkOnlyOne();
	showHideCR();
	setTimeout("popovers()",100);
});

function showHide(d){
	$('#toggle').focus();
	if($('#'+d+'Label').hasClass('active')){
		$('tr.'+d).addClass("hRow");
		$('div.'+d).hide();
		createCookie(d,'off')
		$('#'+d+'Label').removeClass('active');
	} else {
		$('tr.'+d).removeClass("hRow");
		$('div.'+d).show();
		createCookie(d,'on')
		$('#'+d+'Label').addClass('active');
	}
	$('#toggle').focus();
	checkOnlyOne();
	return showHideCR();
}
function checkOnlyOne() {
	$('.hidden_only_one').removeClass('hidden_only_one');
	if ($('h4:visible').length==1)
		$('h4').addClass('hidden_only_one');
}
function createCookie(n,v){
	document.cookie = n+'='+v+'; path=/';
}
function readCookie(n){
	c=document.cookie.split(';');
	for(i=0;i<c.length;i++) {
		if(c[i].indexOf(n+'=')==0)
			return c[i].substring(n.length+1,c[i].length);
	}
	return null;
}
function openWindows(a) {
	p=a.indexOf(',');
	if (p<0) {
		window.open(a);
		loc = document.location.href;
		idx = loc.indexOf('multi_');
		if (idx>0)
			document.location = loc.substring(0, idx);
	} else {
		window.open(a.substring(0,p));
		setTimeout('openWindows("'+a.substring(p+1)+'")',1000);
	}
}
function updateStriping() {
	var g=0;
	$('#week-table tr.odd').removeClass('odd');
	$('#week-table tr:not(.hRow)').each(function(i, r) {
		if (g%2==1) {
			$(r).addClass('odd');
		}
		g++;
	})
};
function loadResults() {
	v=$('#search').val();
	i=0;
	if (v=='') {
		$('.showList').hide();
		$('.showList .panel-heading').hide();
	} else {
		$('div.showList span.tt').each(function() {
			if (this.innerHTML.indexOf(v)!=-1) {
				i=i+1;
				if(i<=max_results)
					$(this).parent().parent().show();
				else
					$(this).parent().parent().hide();
			} else
				$(this).parent().parent().hide()
		});
		$('.showList').show();
		$('.showList .panel-heading').show();
		if (i<=max_results)
			$('#more').hide();
		else 
			$('#more').show();
	}
}

function showHideCR() {
	$('.popover').remove();
	if ($(".col0").length==0)	return;
	$('.loading_wrap').show();
	$('#week-table tr.hiddenLate').removeClass('hiddenLate');
	$('td.hCol').removeClass("hCol");
	onscreen=true;
	for (i=0;i<50;i++) {
		if (onscreen)
			if ($("thead .col"+i).position().left + $("thead .col"+i).outerWidth() > $(window).width())
				onscreen = false;
			else if (typeof week_hide_cols != 'undefined')
				if (week_hide_cols)
					if ($("tr:not(.hRow) td.col"+i+".notempty").length==0)
						$("td.col"+i).addClass("hCol");
		if (!onscreen)
			$("td.col"+i).addClass("hCol");
	}
	$('#week-table tbody tr').each(function() {
		if ($(this).children(".notempty:not(.hCol)").length==0)
			$(this).addClass('hiddenLate');
	});
	updateStriping();
	last_window_width=window.innerWidth;
	$('.loading_wrap').hide();
	return false;
}

function showTab(name) {
	vis = $("#"+name+"Div").css("display") !== "none";
	$("#notepadDiv").hide();
	$("#settingsDiv").hide();
	$("#commentsDiv").hide();
	$("#episodesDiv").hide();
	$("#specialEpisodesDiv").hide();
	$("#notepadTab").removeClass("btn-info");
	$("#settingsTab").removeClass("btn-info");
	$("#commentsTab").removeClass("btn-info");
	$("#episodesTab").removeClass("btn-info");
	$("#specialEpisodesTab").removeClass("btn-info");
	if (!vis) {
		$("#"+name+"Div").show();
		$("#"+name+"Tab").addClass("btn-info");
		
	}
	return false;
}

function saveShow(season, episode) {
	$('#season').val(season);
	$('#episode').val(episode);
	$("form")[0].submit();
	return false;
}
