/**
 * Plan-Script: Kann einfach alles
 * 
 * @author Johannes Gräger
 */
	
var method;
var loggedInAs;
var adminModeActive = false;

$(document).ready(function() {
	
	loggedInAs = $("#loggedInUserAnchor").html();

	$("#adminLinkAnchor").on('tap',function(event) {
		event.preventDefault();
		activateAdminMode(!adminModeActive);
	});
	
	$("#planTable td").each(function() {
		if ($(this).hasClass("tdnonobligatedlink")) {
			if ($(this).html() == loggedInAs) {
				$(this).removeClass();
				$(this).addClass("tdnonobligatedbyuser");
			}
		}
	});
	
	$("#halfshift-btngroup").hide();

/*	$("td").each(function() {
		var cellID = $(this).attr('id');
		if (typeof cellID != "undefined"){
			if (cellID.split("_")[2] == "1") {
				if(typeof $(this).next('td').attr('id') != "undefined") {
					if ($(this).next('td').attr('id').split('_')[2] == "2") {
						$(this).css("border-right","3px solid");	
					}
				}
			}
		}
	});
*/
	
	$("body").on('tap',".tdsuccesslink, .tderrorlink, .tdnonobligatedbyuser, .tdnonobligatedlink",function() {
		var cellID = $(this).attr('id');
		var isNoWeekday = cellID.substr(0,3) != "dow";

		if (isNoWeekday) {
			if ($(this).hasClass("tderrorlink") || $(this).hasClass("tdnonobligatedlink")) {
				openDialog(cellID,true);
			} else {
				openDialog(cellID,false);
			}
		}
	});
	


	//Alles für Textspalten
	$("body").on('tap','td[id^="txt_"]',function() {
		onTextField($(this).attr('id'));
	});


	//Eventhandler für den Bestätigenknopf im regulären austragen Dialog
	$("#btnDialogConfirm").on('tap',ajaxHandler);
});

function submitSpecialDate(date) {
	var splittedDate  = date.split(".");
	var dateForSubmit = splittedDate[2].substr(0,4) + "-" + splittedDate[1] + "-" + splittedDate[0];

	if (new Date(dateForSubmit) < new Date()) {
		return false;
	}
	
	var submitUrl = document.URL.split("/plan")[0] + "/plan/saveSpecialdate/" + dateForSubmit;
	$.ajax({
		type: 'POST',
		content: 'ajax=1',
		url: submitUrl,
		success: function(response) {
			if (response == "200" || response == "210") {
				$.ajax({
					type: 'POST',
					content: 'ajax=0',
					async: false,
					url: document.URL,
					success: function(response) {
						var $responseAsDomElement = $(response);
						var $trContent = $responseAsDomElement.find("#"+dateForSubmit).html();
						$("#insertDiv").remove();

						$("#"+dateForSubmit).html($trContent);
					},
					error: function(response) {
						alert("Leider ist ein Fehler aufgetreten, der die Stabilität der Applikation beeinträchtigt. Bitte laden sie die Seite erneut, da es sonst zu Einschränkungen in der Funktionsfähigkeit kommen kann.");
					}
				});
				$("#"+dateForSubmit+" td").each(function() {
					if (response == "210") {
						if ($(this).hasClass("tdsuccess")  && $(this).attr('id').substr(0,3) != "dow") {
							$(this).removeClass();
							$(this).addClass("tdsuccesschangeable");
						}
					}
				});
				var oldContent = $("#dow_"+dateForSubmit).next().html();
				var dow = new Date(dateForSubmit).getDay();
				if ((response == "200" && (dow != 0 && dow != 6)) || (response == "210" && (dow == 0 || dow == 6))) {
					var newContent = oldContent + "&nbsp;<i class='icon-ok-circle'></i>";
				} else if ((response == "210" && (dow != 0 && dow != 6)) || (response == "200" && (dow == 0 || dow == 6))) {
					var newContent = oldContent + "&nbsp;<i class='icon-ban-circle'></i>";
				} else if (response == "500" || response == "510") {
					alert("Während des Vorgangs ist ein unbekannter Fehler aufgetreten");
				}
				$("#dow_"+dateForSubmit).next().html(newContent);
				$("#dow_"+dateForSubmit).next().on('tap',function() {
					if (confirm('Möchten sie den Status dieses Datums wirklich ändern?')) {
						submitSpecialDate(date);
					}
				});
			}
		},
		error: function(response) {
			alert("Fehler");
		}
	});
}

function activateAdminMode(activate) {
	var today = new Date();
	
	if (activate) {	
		adminModeActive = true;
		alert("Adminmodus aktiviert");
		var today = new Date();
		today.setDate(today.getDate()-1);
		$("td").each(function() {
			var cellID = $(this).attr("id");
			if (typeof cellID != "undefined") {
				var cellDate = new Date(cellID.split('_')[0]);
				if (cellDate.getTime() >= today.getTime() && $("#"+cellID).hasClass("tdsuccess")) {
					$("#"+cellID).removeClass();
					$("#"+cellID).addClass("tdsuccesschangeable");
				}
				if (cellID.substr(0,3) == "dow") {
					var oldContent = $("#"+cellID).next().html();
					var newContent;
					if (new Date($("#"+cellID).parent().attr('id')).getTime() >= today.getTime()) {
						if ($(this).parent().attr('class') == 'activeDay') {
							if ($(this).html() == "Sa" || $(this).html == "So") {
								newContent = oldContent + "&nbsp;<i class='icon-ok-circle'></i>";
							} else {
								newContent = oldContent + "&nbsp;<i class='icon-ban-circle'></i>";
							}
						} else {
							if ($(this).html() == "Sa" || $(this).html == "So") {
								newContent = oldContent + "&nbsp;<i class='icon-ban-circle'></i>";
							} else {
								newContent = oldContent + "&nbsp;<i class='icon-ok-circle'></i>";
							}
						}
						$(this).next().html(newContent);
						$(this).next().on('tap',function() {
							if (confirm('Möchten sie den Status dieses Datums wirklich ändern?')) {
								submitSpecialDate(oldContent);
							}
						});
					}
				}
			}
			

		});
		
		$("body").off('tap');
		$("body").on('tap',".tdsuccesslink, .tderrorlink, .tdsuccesschangeable, .tdnonobligated, .tdnonobligatedlink, .tdnonobligatedbyuser",function() {
			onTextField($(this).attr('id'));
		});
		$("body").on('tap','td[id^="txt_"]',function() {
			onTextField($(this).attr('id'));
		});
	} else {
		alert("Adminmodus deaktiviert");
		$(".icon-ban-circle, .icon-ok-circle").remove();
		adminModeActive = false;
		$("input:not([type=hidden])").remove();
		$("body").off('tap','td[id^="txt_"]');
		$("body").off('tap');
		$(".tdsuccesschangeable").each(function() {
			$(this).removeClass();
			$(this).addClass("tdsuccess");
		});
		$("body").on('tap','td[id^="txt_"]',function() {
			onTextField($(this).attr('id'));
		});
		$("td").each(function() { $(this).off(); });
		$("body").on('tap',".tdsuccesslink, .tderrorlink, .tdnonobligatedlink,.tdnonobligatedbyuser",function() {
			var cellID = $(this).attr('id');
			var isNoWeekday = cellID.substr(0,3) != "dow";

		
			if (isNoWeekday) {
				if ($(this).hasClass("tderrorlink") || $(this).hasClass("tdnonobligatedlink")) {
					openDialog(cellID,true);
				} else {
					openDialog(cellID,false);
				}
			}
		});
	}

}

function checkIfDateIsComplete(date) {
	var str = document.URL.split("/plan");
	var validationUrl = str[0] + "/plan/datecomplete/" + date;

	$.ajax( {
		type: 'POST',
		data: "ajax=1",
		url: validationUrl,
		error: function(response) {
			alert("Ein Fehler ist aufgetreten. Bitte laden sie die Seite erneut.");
		},
		success: function(response) {
			$("#dow_"+date).removeClass();
			if (response == "true") {
				$("#dow_"+date).addClass("tdsuccess");
			} else {
				$("#dow_"+date).addClass("tderrorlink");
			}
		}
			
	});
}

function onTextField(tdID) {
	if (tdID.split("_")[0] == "dow") {
		//Wochentagsfelder sollen nicht editierbar sein
		return false;
	}
	
	//Daten die bereits Vergangen sind sollen nicht mehr editiert werden dürfen
	var today = new Date();
	today.setDate(today.getDate()-1);
	var oldValue = $("#"+tdID).html();
	if(tdID.match("txt_*")) {
		if (today.getTime() > new Date(tdID.split("_")[1]).getTime()) return false;
	} else {
		if (today.getTime() > new Date(tdID.split("_")[0]).getTime()) return false;
	}
	
	var $th = $("#"+tdID).closest('table').find('th').eq($("#"+tdID).index());
	//Adminfelder sollen nur im Adminmodus bearbeitbar sein!
	if ($th.attr('admin') == "true") {
		if (!adminModeActive) return false;
	}
	var cellID = "textfield_" + $("#"+tdID).attr('id');
	var newTextField = $("<input type='text' id='"+cellID+"'></input>");
	var oldContent = $("#"+tdID).html();

	$("#"+tdID).html(newTextField);
//	$("body").off('tap',".tdsuccesslink, .tderrorlink");
	$("body").off('tap');
	$("#"+cellID).focus();
	$("#"+cellID).on('keypress',function(event) {
		var keycode = (event.keyCode ? event.keyCode : event.which);
		if (keycode == 13) {
			var str = cellID.split("_");
			if (str[1] != "txt") {
				var username = $("#"+cellID).val();
				var requestUrl = document.URL.split('/plan')[0] + "/plan/saveUserEntry/" + str[1] + "/" + str[2] + "/" + str[3] + "/" + username;
				$.ajax({
					type: 'POST',
					url: requestUrl,
					data: 'ajax=1',
					success: function(response) {
						if (response == "200") {
							var $td = (newTextField).parent();
							$(newTextField).remove();
							if ($td.attr('class').substr(0,14) == "tdnonobligated") {
									$td.removeClass();
								if (username == loggedInAs) {
									$td.addClass("tdnonobligatedbyuser");
								} else {
									$td.addClass("tdnonobligated");
								}
							} else {
								$td.removeClass();
								if (username == loggedInAs) {
									$td.addClass("tdsuccesslink");
								} else {
									$td.addClass("tdsuccesschangeable");
								}
							}
							$td.html(username);
							$("body").off('tap');
							$("#"+cellID).off('keypress');
							$("body").on('tap',".tdsuccesslink, .tderrorlink, .tdsuccesschangeable, .tdnonobligated, .tdnonobligatedlink, .tdnonobligatedbyuser",function() {
								onTextField($(this).attr('id'));
							});
							$("body").on('tap','td[id^="txt_"]',function() {
								onTextField($(this).attr('id'));
							});
						} else if (response == "210") {
							var $td = (newTextField).parent();
							$(newTextField).remove();
							if ($td.attr('class').substr(0,14) == "tdnonobligated") {
								$td.removeClass();
								$td.addClass("tdnonobligatedlink");
							} else {
								$td.removeClass();
								$td.addClass("tderrorlink");
							}
							$td.html("");
							$("body").off('tap');
							$("#"+cellID).off('keypress');
							$("body").on('tap',".tdsuccesslink, .tderrorlink, .tdsuccesschangeable, .tdnonobligated, .tdnonobligatedlink, .tdnonobligatedbyuser",function() {
								onTextField($(this).attr('id'));
							});
							$("body").on('tap','td[id^="txt_"]',function() {
								onTextField($(this).attr('id'));
							});
						} else if (response == "403") {
							alert("Sie haben keine Berechtigung für diese Aktion! Der Adminmodus wird deaktiviert.");
							activateAdminMode(false);						
						} else if (response == "510") {
							alert("Während des Vorgangs ist ein unbekannter Fehler aufgetreten.");
						} else if (response == "404") {
							alert("Fehler: Der Benutzer wurde nicht gefunden");			
						}
					},
					error: function() {
						alert("Ein unbekannter Fehler ist aufgetreten. Der AdminModus wurde deaktiviert!");
						activateAdminMode(false);
					},
					complete: function() {
						checkIfDateIsComplete(str[1]);
					}
					});
				} else {
					$("#"+cellID).attr('disabled',true);
					var content = $("#"+cellID).val();
					if (content == "" && oldContent == "") {
						$(newTextField).remove();
						$("#"+cellID).off('keypress');
						$("body").on('tap',".tdsuccesslink, .tderrorlink, .tdsuccesschangeable, .tdnonobligated, .tdnonobligatedlink, .tdnonobligatedbyuser",function() {
							onTextField($(this).attr('id'));
						});
						$("body").on('tap','td[id^="txt_"]',function() {
								onTextField($(this).attr('id'));
						});
						return false;
					}
					var requestUrl = document.URL.split('/plan')[0] + "/plan/saveTextEntry/" + str[2] + "/" + str[3] + "/" + content;
					$.ajax( {
						type: 'POST',
						url: requestUrl,
						async: false,
						content: 'ajax=1',
						success: function(response) {
							if (response == "200" || response == "210") {
								var $td = $(newTextField).parent();
								$(newTextField).remove();
								if ($td.hasClass('tderror')) {
									$td.removeClass();
									$td.addClass('tdsuccess');
								} else if ($td.hasClass('tdsuccess')) {
									$td.removeClass();
									$td.addClass('tderror');
								}
								$td.html(content);
							} else if (response == "403") {
								alert("Sie verfügen nicht über ausreichende Berechtigung für Operation! Der Adminmodus wurde deaktiviert.")
								activateAdminMode(false);
							} else if (response == "404") {
								alert("Der Benutzer wurde nicht gefunden");			
							} else {
								alert ("Unknown Response Code: ["+response+"]");
							}
							$("body").off('tap');
							$("#"+cellID).off('keypress');
							$("body").on('tap',".tdsuccesslink, .tderrorlink, .tdsuccesschangeable, .tdnonobligated, .tdnonobligatedlink, .tdnonobligatedbyuser",function() {
								onTextField($(this).attr('id'));
							});
							$("body").on('tap','td[id^="txt_"]',function() {
									onTextField($(this).attr('id'));
							});
							checkIfDateIsComplete(str[2]);	
						},
						error: function(response) {
							alert("Ein unbekannter Fehler ist aufgetreten. Der Adminmodus wurde deaktiviert");
							activateAdminMode(false);
						}
				});
			}
		} else if (keycode == 27) {
			newTextField.remove();
			$("#"+tdID).html(oldValue);	
			$("body").on('tap',".tdsuccesslink, .tderrorlink, .tdsuccesschangeable, .tdnonobligated, .tdnonobligatedlink, .tdnonobligatedbyuser",function() {
				onTextField($(this).attr('id'));
			});
			$("body").on('tap','td[id^="txt_"]',function() {
				onTextField($(this).attr('id'));
			});
		}
	});
}

function ajaxHandler(cellID) {
	var username;

	$("#btnDialogConfirm").button('loading');
	var cellID = $("#cellID").val();
	if (cellID == '') {
		alert("Fehlercode #01 \n Bitte versuchen sie es erneut");
	} else {
		
		var date = cellID.substr(0,10);
		var columnID = cellID.split("_")[1];
		var halfshift;

		if (method == "in") {
			username = $("#usernameHidden").val();
			halfshift = $("#halfshift-btngroup").find("button.active").prop('value');
		} else {
			var username = "";
			if (typeof cellID.split("_")[2] != 'undefined') {
				halfshift = cellID.split("_")[2];
			} else {
				halfshift = "3";
			}
		}
		var str = document.URL.split('/plan');
			var requestUrl = str[0] + "/plan/saveUserEntry/" + date + "/" + columnID + "/" + halfshift + "/";
			
		if (username != "") requestUrl += username;
		$.ajax( {
			type: 'POST',
			url: requestUrl,
			data: "ajax=1",
			success: function(response) {
				if (response == "200") {
					if ($("#"+cellID).hasClass("tdnonobligatedlink")) {
						 $("#"+cellID).removeClass();
						 $("#"+cellID).addClass("tdnonobligatedbyuser");
					} else {
					 $("#"+cellID).removeClass();
						$("#"+cellID).addClass("tdsuccesslink");
					}
					 $("#"+cellID).text(username);
				} else if (response == "210") {
					var newClass;
					if ($("#"+cellID).hasClass("tdnonobligatedbyuser")) {
						 newClass = "tdnonobligatedlink";
					} else {
						newClass = "tderrorlink"
					}
					$("#"+cellID).text("");
					$("#"+cellID).removeClass();
					$("#"+cellID).addClass(newClass);
				} else {
					alert ("Unknown response code: " + response);
				}
			},
			error: function(response) {
				alert ("Unbekannter Fehler");
			},
			complete: function() {
				checkIfDateIsComplete(date);
			}
			
		});
	}
	
$("#btnDialogConfirm").button('reset');
$("#modalMenu").modal('hide');
$("#halfshift-btngroup").show();
return false;
}

	
	function openDialog(cellID,eintragen) {
			$("#sevenDayWarning").hide();
			$("#btn-first").removeClass('active');
			$("#btn-first").show();
			$("#btn-second").removeClass('active');
			$("#btn-second").show();
			$("#btn-full").removeClass('active');
			$("#btn-full").show();
			
			$("#methodAnchor").html("");
			$("#dateAnchor").html("");
			$("#shiftAnchor").html("");
			
			var formatDate = new Date(cellID.substr(0,10));
			$("#dateAnchor").html(formatDate.getDate() + "." + (formatDate.getMonth()+1) + "." + formatDate.getFullYear());
			
			if (cellID.substr(0, 3) == "txt") {
				// Es handelt sich um eine Textspalte
			} else {
				// Es handelt sich um eine Benutzerspalte
				if (eintragen) {
					$("#methodAnchor").html("Eintragen");
					//Es sollen nur Optionen angezeigt werden, welche zutreffen
					if (typeof cellID.split("_")[2] != 'undefined') {
						$("#halfshift-btngroup").hide();
						var lastChar = cellID.charAt(cellID.length -1);
						if (lastChar == "1") {
							$("#btn-first").addClass('active');
							$("#btn-full").hide();
							$("#btn-second").hide();
						}
						if (lastChar == "2") {
							$("#btn-second").addClass('active');
							$("#btn-full").hide();
							$("#btn-first").hide();
						}
					} else {
						$("#btn-full").addClass('active');
					}
					$("#modalMenuLabel").html("Eintragen");
					$("#btnDialogConfirm").html("Eintragen");
					method = "in";
					$("#halfshift-btngroup").show();
				} else {
					//Hier geht es jetzt ums austragen
					var dateObj = new Date();
					dateObj.setDate(dateObj.getDate()+7);
					var tmpDate = new Date(formatDate);
					
					if (tmpDate.toDateString() != (new Date()).toDateString() && tmpDate.getTime() <= dateObj.getTime() && ! $("#" + cellID).hasClass("tdnonobligatedbyuser")) {
						$("#sevenDayWarning").show();
					}
					$("#methodAnchor").html("Austragen");
					$("#modalMenuLabel").html("Austragen");
					$("#btnDialogConfirm").html("Austragen");
					method = "out";
					$("#halfshift-btngroup").hide();
				}
				
				//Finde den TableHeader zu der entsprechenden Schicht:
				var $td = $("#"+cellID);
				var $th = $td.closest('table').find('th').eq($td.index()+2);
				$("#shiftAnchor").html($th.html());
						
				$("#cellID").val(cellID);
				$("#modalMenu").modal('show');
	}
}
