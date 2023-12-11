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

    let data = {
        url: url_api,
        contentType: 'application/json',
        dataType: 'json',
        data: JSON.stringify(form_object),
    }

    return ajax_callback_post(data)
}


function activate_license_key(ajaxurl, $setup_code, sec) {

    let data = {
        action: 'app_setup',
        code:   $setup_code,
        sec:    sec,
    }

    return ajax_callback_post(ajaxurl, data)
}


function is_valid_email(email) {

    let email_regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return email_regex.test(email);
}

function ajax_callback_post(ajaxurl = null, data) {

    return new Promise(function(resolve, reject) {
        jQuery.post(ajaxurl, data, function(response) {

            if ((response && ('success' in response) && response.success === false)) {
                reject(response);
            } else if (response.error) {
                reject(response);
            }
            else  {
                resolve(response);
            }
        });
    });
}
