// ;(function($){
//   "use strict";
//
//   window.llar = {
//     progressbar: {
//       timeouts: [],
//       $bar: null,
//       $fill: null,
//       start: function() {
//
//         if($('body').find('#llar-progress-bar').length) {
//
//           this.$bar = $('body').find('#llar-progress-bar');
//
//         } else {
//
//           this.$bar = $('<div id="llar-progress-bar"><span></span></div>');
//
//           $('body').prepend(this.$bar);
//         }
//
//         this.clearTimeouts();
//
//         this.$fill = this.$bar.find('span');
//
//         this.timeouts.push(setTimeout(function(){llar.progressbar.percent(35);}, 100));
//         this.timeouts.push(setTimeout(function(){llar.progressbar.percent(60);}, 800));
//         this.timeouts.push(setTimeout(function(){llar.progressbar.percent(75);}, 1400));
//         this.timeouts.push(setTimeout(function(){llar.progressbar.percent(80);}, 1800));
//         this.timeouts.push(setTimeout(function(){llar.progressbar.percent(85);}, 2200));
//         this.timeouts.push(setTimeout(function(){llar.progressbar.percent(95);}, 2600));
//
//       },
//       percent: function(val) {
//         this.$fill.css('width', val + '%');
//       },
//       clearTimeouts: function() {
//         this.timeouts.forEach(function (t) {
//           clearTimeout(t);
//         });
//       },
//       stop: function() {
//
//         this.clearTimeouts();
//
//         this.percent(100);
//
//         setTimeout(function () {
//           llar.progressbar.$bar.remove();
//         }, 500);
//       }
//     }
//   };
//
//   $(document).ready(function(){
//
//   });
//
// })(jQuery);

function activate_micro_cloud(email) {
    let url_api = 'https://api.limitloginattempts.com/checkout-staging/network';
    // let url_api = ''https://api.limitloginattempts.com/checkout/network'';

    let form_data = [];
    form_data.push({name: 'group', value: 'free'});
    form_data.push({name: 'email', value: email});

    let form_object = form_data.reduce(function(object, item) {
        object[item.name] = item.value;
        return object;
    }, {});


    return new Promise(function(resolve, reject) {
        jQuery.post({
            url: url_api,
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify(form_object),
        }, function (response) {

            if (response) {
                resolve(response);
            } else {
                reject(response);
            }
        });
    });
}

function activate_license_key(ajaxurl, $setup_code, sec) {

    return new Promise(function(resolve, reject) {
        jQuery.post(ajaxurl, {
            action: 'app_setup',
            code:   $setup_code,
            sec:    sec,
        }, function(response) {

            if (response.success) {
                resolve(response);
            } else {
                reject(response);
            }
        });
    });
}

function is_valid_email(email) {

    let email_regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return email_regex.test(email);
}
