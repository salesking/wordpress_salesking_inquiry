jQuery(document).ready(function ($) {

    // set up line item removal
    $('#skinquiry_productlist').on('click','.skinquiry_delete',function(e) {
        $(this).parents('.skinquiry_product').remove();
    });

    // set up line item generation
    $('#skinquiry_addproduct').click(function(e) {
        e.preventDefault();

        var id = parseInt($('#skinquiry_rowid').val()) + 1;
        $('#skinquiry_rowid').val(id);

        var product = $('<div class="skinquiry_product">' +
            '<label for="skinquiry_products_'+id+'_product">'+objectL10n.product+'</label> ' +
            '<select id="skinquiry_products_'+id+'_product" name="skinquiry_products['+id+'][product]"></select> ' +
            '<label for="skinquiry_products_'+id+'_quantity">'+objectL10n.quantity+'</label> ' +
            '<input id="skinquiry_products_'+id+'_quantity" name="skinquiry_products['+id+'][quantity]" value="1" type="text" /> ' +
            '<span class="skinquiry_delete">'+objectL10n.remove+'</span>' +
            '</div>');

        product.find('select').html($('#skinquiry_products').html())

        $('#skinquiry_productlist').append(product);
    });

    // set up validation
    $('#skinquiry_form').validate({
        rules: {
            skinquiry_client_email: {
                email: true,
                required: true
            }
        }
    });
});