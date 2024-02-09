(function(Icinga) {
	var Rrdtool = function(module) {
		this.module = module;
		this.initialize();
		this.module.icinga.logger.debug("Rrdtool module loaded");
	};
	Rrdtool.prototype = {
		initialize: function() {
			this.module.on("rendered", this.onRendered);
			$(document).on("submit", "#customRange", this.onSubmit);
		},
		onRendered: function(event) {
			$("div.graph").each(function() {
				var current = $(this);
				$.ajax({url: current.next().attr("src").replace("range=" + current.attr("data-range"), "meta"), cache: true, success: function(data) {
						data = data.split(/[x:]/);
						if (data.length === 4) {
							data[3] = parseInt(data[3]) + 2;
							current.css("left", data[0]);
							current.css("top", data[1]);
							current.css("width", data[2]);
							current.css("height", data[3]);
							current.css("cursor", "ew-resize");
							current.imgAreaSelect({autoHide: true, fadeSpeed: 500, handles: false, minHeight: data[3], onSelectEnd: Rrdtool.zoom, parent: ".content"});
						}
					}
				});
			});
		},
		onSubmit: function(event) {
			event.preventDefault();
			var start = new Date($("#start").val()).getTime() / 1000;
			var end = new Date($("#end").val()).getTime() / 1000;
			if (end - start < 600) {
				var diff = Math.floor((600 - (end - start)) / 2);
				start -= diff;
				end += diff;
			}
			if (window.location.href.indexOf("range=" + $("div.graph").first().attr("data-range")) === -1) {
				window.location += "&range=" + start + "-" + end;
			} else {
				window.location = window.location.href.replace("range=" + $("div.graph").first().attr("data-range"), "range=" + start + "-" + end);
			}
			event.submit();
		}
	};
	Rrdtool.zoom = function(img, selection) {
		var start = parseInt($(img).attr("data-start"));
		var seconds = (parseInt($(img).attr("data-end")) - start) / parseInt($(img).css("width"));
		var left = Math.floor(start + selection.x1 * seconds);
		var right = Math.ceil(start + selection.x2 * seconds);
		if (right - left < 600) {
			var diff = Math.floor((600 - (right - left)) / 2);
			left -= diff;
			right += diff;
		}
		if (window.location.href.indexOf("range=" + $(img).attr("data-range")) === -1) {
			window.location += "&range=" + left + "-" + right;
		} else {
			window.location = window.location.href.replace("range=" + $(img).attr("data-range"), "range=" + left + "-" + right);
		}
	};
	Icinga.availableModules.rrdtool = Rrdtool;
}(Icinga));
