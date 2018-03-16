/**
 * Created by Boxedsolutions on 2016-12-07.
 */
window.method = null;
window.address_selected = null;
window.latestScroll = null;
var BillmateIframe = new function(){
    var self = this;
    var childWindow = null;
    var timerPostMessage;

    this.updateAddress = function (data) {
        // When address in checkout updates;
        self.lock();
        jQuery.ajax({
            url : UPDATE_ADDRESS_URL,
            data: data,
            type: 'POST',
            success: function(response){

                jQuery('#shipping-container').html(response);
                if(jQuery('input[name="estimate_method"]:checked').length != 1){
                    jQuery('input[name="estimate_method"]:first').click();
                }
                
                window.address_selected = true;
                self.unlock();
            }
        });

    };
    this.updatePaymentMethod = function(data){
        if(window.method != data.method) {
            jQuery.ajax({
                url: UPDATE_PAYMENT_METHOD_URL,
                data: data,
                type: 'POST',
                success: function (response) {
                    var result = response.evalJSON();
                    if (result.success) {
                        if(result.hasOwnProperty("update_checkout") && result.update_checkout === true)
                            self.updateCheckout();
                        if(data.method == 8 || data.method == 16)
                            self.updateCheckout();

                        window.method = data.method;

                    }
                }
            });
        }
        
    };
    this.updateShippingMethod = function(){

    }
    this.createOrder = function(data){
      // Create Order
            jQuery.ajax({
                url : CREATE_ORDER_URL,
                data: data,
                type: 'POST',
                success: function(response){
                    var result = response.evalJSON();
                    location.href=result.url;
                }
            });

    };
    this.updateTotals = function(update){
        jQuery.ajax({
            url : UPDATE_TOTALS_URL,
            type: 'POST',
            success: function(response){
                jQuery('#billmate-totals').html(response);
                if(update){
                    b_iframe.updateCheckout();
                }

            }
        });
    };
    this.initListeners = function () {
        document.observe('dom:loaded',function () {
            window.addEventListener("message",self.handleEvent);
        });
    }
    this.handleEvent = function(event){
        if(event.origin == "https://checkout.billmate.se") {
            try {
                var json = JSON.parse(event.data);
            } catch (e) {
                return;
            }
            self.childWindow = json.source;
            switch (json.event) {
                case 'address_selected':
                    self.updateAddress(json.data);
                    break;
                case 'checkout_success':
                    self.createOrder(json.data);
                    self.unlock();
                    break;
                case 'content_height':
                    $('checkout').height = json.data;
                    break;
                case 'content_scroll_position':
                    window.latestScroll = jQuery(document).find( "#checkout" ).offset().top + json.data;
                    jQuery('html, body').animate({scrollTop: jQuery(document).find( "#checkout" ).offset().top + json.data}, 400);
                    break;
                case 'checkout_loaded':
                    self.unlock();
                    break;
                default:
                    break;
            }
        }

    };

    this.checkoutPostMessage = function(message) {
        var win = document.getElementById('checkout').contentWindow;
        win.postMessage(message,'*')
    }

    this.updateCheckout = function(){
        this.lock();
        this.checkoutPostMessage('update');
    }

    this.lock = function() {
        that = this;
        clearTimeout(this.timerPostMessage);
        var wait = setTimeout(function() {
            that.checkoutPostMessage('lock');
        }, 500);
        this.timerPostMessage = wait;
    }

    this.unlock = function() {
        that = this;
        clearTimeout(this.timerPostMessage);
        var wait = setTimeout(function() {
            that.checkoutPostMessage('unlock');
        }, 500);
        this.timerPostMessage = wait;
    }
};

jQuery(document).ready(function(){
    jQuery('.qty').on('change',function(e){
        b_iframe.lock();
        jQuery('.qty').closest('form').append('<input name="return_url" type="hidden" value="'+CHECKOUT_URL+'"/>');
        jQuery('.btn-update').click();
    });
});

var b_iframe = BillmateIframe;
b_iframe.initListeners();
b_iframe.lock();
