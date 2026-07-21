function getURLVar(key) {
	var value = [];

	var query = String(document.location).split('?');

	if (query[1]) {
		var part = query[1].split('&');

		for (i = 0; i < part.length; i++) {
			var data = part[i].split('=');

			if (data[0] && data[1]) {
				value[data[0]] = data[1];
			}
		}

		if (value[key]) {
			return value[key];
		} else {
			return '';
		}
	}
}

$(document).ready(function() {
	//Form Submit for IE Browser
	$('button[type=\'submit\']').on('click', function() {
		$("form[id*='form-']").submit();
	});

	// Highlight any found errors
	$('.text-danger').each(function() {
		var element = $(this).parent().parent();

		if (element.hasClass('form-group')) {
			element.addClass('has-error');
		}
	});

	// tooltips on hover
	$('[data-toggle=\'tooltip\']').tooltip({container: 'body', html: true});

	// Makes tooltips work on ajax generated content
	$(document).ajaxStop(function() {
		$('[data-toggle=\'tooltip\']').tooltip({container: 'body'});
	});

	$.event.special.remove = {
		remove: function(o) {
			if (o.handler) {
				o.handler.apply(this, arguments);
			}
		}
	}
	
	$('#button-menu').on('click', function(e) {
		e.preventDefault();
		
		$('#column-left').toggleClass('active');
	});

	// Set last page opened on the menu
	$('#menu a[href]').on('click', function() {
		sessionStorage.setItem('menu', $(this).attr('href'));
	});

	if (!sessionStorage.getItem('menu')) {
		$('#menu #dashboard').addClass('active');
	} else {
		// Sets active and open to selected page in the left column menu.
		$('#menu a[href=\'' + sessionStorage.getItem('menu') + '\']').parent().addClass('active');
	}
	
	$('#menu a[href=\'' + sessionStorage.getItem('menu') + '\']').parents('li > a').removeClass('collapsed');
	
	$('#menu a[href=\'' + sessionStorage.getItem('menu') + '\']').parents('ul').addClass('in');
	
	$('#menu a[href=\'' + sessionStorage.getItem('menu') + '\']').parents('li').addClass('active');
	
	// Image Manager
	$(document).on('click', '#button-thumb-image', function() {
		var $element = $(this);
	
            imagemanager();
		
			function imagemanager() {
				var $button = $(this);
				var $icon   = $button.find('> i');

				$('#modal-image').remove();

				$.ajax({
					url: 'index.php?route=common/filemanager&user_token=' + getURLVar('user_token') + '&target=' + $element.parent().find('input').attr('id') + '&thumb=' + $element.attr('id'),
					dataType: 'html',
					
					beforeSend: function() {
						$button.prop('disabled', true);
						if ($icon.length) {
							$icon.attr('class', 'fa fa-circle-o-notch fa-spin');
						}
					},
					
					complete: function() {
						$button.prop('disabled', false);

						if ($icon.length) {
							$icon.attr('class', 'fa fa-pencil');
						}
					},
					success: function(html) {
						$('body').append('<div id="modal-image" class="modal">' + html + '</div>');

						$('#modal-image').modal('show');
					}
				});

				$element.popover('destroy');
			}

			
	});
	
});

+function($) {
  $.fn.buttonanimate = function(state) {
    return this.each(function() {
      var $element = $(this);

      if (state === 'loading') {
        $element.data('html', $element.html());
        $element.data('disabled', $element.prop('disabled'));
        $element.prop('disabled', true).width($element.width()).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');
      }

      if (state === 'reset') {
        $element.prop('disabled', $element.data('disabled')).width('').html($element.data('html'));
      }
    });
  }
}(jQuery);

// Autocomplete */
(function($) {
	$.fn.autocomplete = function(option) {
		return this.each(function() {
			var $this = $(this);
			var $dropdown = $('<ul class="dropdown-menu"/>');

			this.timer = null;
			this.items = [];

			$.extend(this, option);

			$this.attr('autocomplete', 'off');

			// Focus
			$this.on('focus', function() {
				this.request();
			});

			// Blur
			$this.on('blur', function() {
				setTimeout(function(object) {
					object.hide();
				}, 200, this);
			});

			// Keydown
			$this.on('keydown', function(event) {
				switch(event.keyCode) {
					case 27: // escape
						this.hide();
						break;
					default:
						this.request();
						break;
				}
			});

			// Click
			this.click = function(event) {
				event.preventDefault();

				var value = $(event.target).parent().attr('data-value');

				if (value && this.items[value]) {
					this.select(this.items[value]);
				}
			}

			// Show
			this.show = function() {
				var pos = $this.position();

				$dropdown.css({
					top: pos.top + $this.outerHeight(),
					left: pos.left
				});

				$dropdown.show();
			}

			// Hide
			this.hide = function() {
				$dropdown.hide();
			}

			// Request
			this.request = function() {
				clearTimeout(this.timer);

				this.timer = setTimeout(function(object) {
					object.source($(object).val(), $.proxy(object.response, object));
				}, 200, this);
			}

			// Response
			this.response = function(json) {
				var html = '';
				var category = {};
				var name;
				var i = 0, j = 0;

				if (json.length) {
					for (i = 0; i < json.length; i++) {
						// update element items
						this.items[json[i]['value']] = json[i];

						if (!json[i]['category']) {
							// ungrouped items
							html += '<li data-value="' + json[i]['value'] + '"><a href="#">' + json[i]['label'] + '</a></li>';
						} else {
							// grouped items
							name = json[i]['category'];
							if (!category[name]) {
								category[name] = [];
							}

							category[name].push(json[i]);
						}
					}

					for (name in category) {
						html += '<li class="dropdown-header">' + name + '</li>';

						for (j = 0; j < category[name].length; j++) {
							html += '<li data-value="' + category[name][j]['value'] + '"><a href="#">&nbsp;&nbsp;&nbsp;' + category[name][j]['label'] + '</a></li>';
						}
					}
				}

				if (html) {
					this.show();
				} else {
					this.hide();
				}

				$dropdown.html(html);
			}

			$dropdown.on('click', '> li > a', $.proxy(this.click, this));
			$this.after($dropdown);
		});
	}
})(window.jQuery);

// Chain ajax calls.
class Chain {
    constructor() {
        this.start = false;
        this.data = [];
    }

    attach(call) {
        this.data.push(call);

        if (!this.start) {
            this.execute();
        }
    }

    execute() {
        if (this.data.length) {
            this.start = true;

            var call = this.data.shift();

            var jqxhr = call();

            jqxhr.done(function () {
                chain.execute();
            });
        } else {
            this.start = false;
        }
    }
}

var chain = new Chain();

function alerterror(error) {
    $('#alert').prepend(
        '<div class="alert border-0 border-danger border-start border-1 bg-light-danger alert-dismissible fade show py-1">' +
            '<div class="d-flex align-items-center">' +
                '<div class="fs-3 text-danger"><i class="bi bi-x-circle-fill"></i></div>' +
                '<div class="ms-3"><div class="text-dynamic">' + error + '</div></div>' +
            '</div>' +
            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
        '</div>'
    );
}

function alertsuccess(success) {
    $('#alert').append(
        '<div class="alert border-0 border-success border-start border-1 bg-light-success alert-dismissible fade show py-1">' +
        '<div class="d-flex align-items-center">' +
        '<div class="fs-3 text-success"><i class="bi bi-check-circle-fill"></i></div>' +
        '<div class="ms-3"><div class="text-dynamic">' + success + '</div></div>' +
        '</div>' +
        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
        '</div>'
    );
}

//MODAL Z-İNDEX DÜZENLEMESİ
document.addEventListener('DOMContentLoaded', function () {
    document.addEventListener('show.bs.modal', function () {
        setTimeout(() => {
            const backdrops = document.querySelectorAll('.modal-backdrop');
            
            if (backdrops.length > 1) {
                for (let i = 1; i < backdrops.length; i++) {
                    backdrops[i].remove();
                }
            }
        }, 50);
    });

    document.addEventListener('hidden.bs.modal', function () {
        const openModals = document.querySelectorAll('.modal.show');
        const backdrops = document.querySelectorAll('.modal-backdrop');
        
        if (openModals.length > 0 && backdrops.length === 0) {
            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            document.body.appendChild(backdrop);
        }
        
        if (openModals.length === 0 && backdrops.length > 0) {
            backdrops.forEach(b => b.remove());
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('padding-right');
        }
    });
});

