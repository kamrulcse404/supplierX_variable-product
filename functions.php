<?php

/**
 * Enqueue script and styles for child theme
 */
function woodmart_child_enqueue_styles()
{
    wp_enqueue_style('child-style', get_stylesheet_directory_uri() . '/style.css', array('woodmart-style'), woodmart_get_theme_info('Version'));
}
add_action('wp_enqueue_scripts', 'woodmart_child_enqueue_styles', 10010);

/*Description name change*/

add_filter('woocommerce_product_description_heading', 'wtwh_rename_description_tab_heading');
function wtwh_rename_description_tab_heading()
{
    return '';
}

add_filter('woocommerce_product_description_tab_title', 'wtwh_rename_description_tab');
function wtwh_rename_description_tab($title)
{
    $title = 'Details';
    return $title;
}

/*Description name change*/

// /*checkout_fields*/
// function quadlayers_remove_checkout_fields( $fields ) {

//  unset($fields['billing']['billing_company']);
// //  unset($fields['billing']['billing_address_1']);
//  unset($fields['billing']['billing_address_2']);
//  unset($fields['billing']['billing_city']);
// //  unset($fields['billing']['billing_postcode']);
//  unset($fields['billing']['billing_country']);
// //  unset($fields['billing']['billing_state']);

//  return $fields; 
// }
// add_filter( 'woocommerce_checkout_fields' , 'quadlayers_remove_checkout_fields' );
/*checkout_fields*/


// Display the minimum price for variable products
add_filter('woocommerce_variable_sale_price_html', 'woosuite_custom_variable_product_price', 10, 2);
add_filter('woocommerce_variable_price_html', 'woosuite_custom_variable_product_price', 10, 2);

function woosuite_custom_variable_product_price($price, $product)
{
    // Check if it's a variable product
    if ($product->is_type('variable')) {
        // Get all variations for the product
        $variations = $product->get_available_variations();

        // Loop through variations to find the minimum price
        $min_price = null;
        foreach ($variations as $variation) {
            $variation_price = floatval($variation['display_price']);

            // Set the initial minimum price or update it if a lower price is found
            if ($min_price === null || $variation_price < $min_price) {
                $min_price = $variation_price;
            }
        }

        // Display the minimum price
        if ($min_price !== null) {
            $price = wc_price($min_price);
        }
    }

    return $price;
}
// Display the minimum price for variable products

// Register the sub-menu under WooCommerce menu
add_action("admin_menu", "wpturbo_register_submenu_page");
function wpturbo_register_submenu_page()
{
    add_submenu_page(
        "woocommerce",
        "Checkout Fields",
        "Checkout Fields",
        "manage_options",
        "wpturbo-checkout-fields",
        "wpturbo_checkout_fields_options_page"
    );
}

// Register settings
add_action("admin_init", "wpturbo_register_settings");
function wpturbo_register_settings()
{
    register_setting(
        "wpturbo_checkout_fields",
        "wpturbo_checkout_fields_options",
        "wpturbo_checkout_fields_sanitize"
    );
}

// Sanitize the input before saving
function wpturbo_checkout_fields_sanitize($input)
{
    // Ensure all expected options are present in the array by checking against the default fields
    $fields = WC()
        ->checkout()
        ->get_checkout_fields();
    $new_input = [];
    foreach ($fields as $section => $field_group) {
        foreach ($field_group as $key => $field) {
            // If the key exists in our submitted input, use that value, otherwise, this field is not hidden
            $new_input[$key] = isset($input[$key]) ? "1" : "0";
        }
    }
    return $new_input;
}

// The settings page content
function wpturbo_checkout_fields_options_page()
{
    if (!current_user_can("manage_options")) {
        return;
    }

    if (isset($_GET["settings-updated"])) {
        add_settings_error(
            "wpturbo_messages",
            "wpturbo_message",
            __("Settings Saved", "wpturbo"),
            "updated"
        );
    }

    settings_errors("wpturbo_messages");
    $options = get_option("wpturbo_checkout_fields_options", []);
?>
    <div class="wrap">
        <h1><?php esc_html_e("Checkout Fields", "wpturbo"); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields("wpturbo_checkout_fields");
            do_settings_sections("wpturbo_checkout_fields");
            $fields = WC()
                ->checkout()
                ->get_checkout_fields();

            // Inline CSS for styling the settings page
            echo '<style>
                .wpturbo-checkout-fields-wrapper {
                    background-color: #f7f7f7;
                    padding: 20px;
                    border-radius: 5px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                }
                .wpturbo-checkout-fields-wrapper h2 {
                    color: #333;
                    border-bottom: 1px solid #ddd;
                    padding-bottom: 5px;
                }
                .wpturbo-checkout-fields-wrapper label {
                    display: block;
                    margin: 10px 0;
                    line-height: 1.6;
                }
                .wpturbo-checkout-fields-wrapper input[type="checkbox"] {
                    margin-right: 10px;
                }
                .form-table th {
                    width: auto;
                    padding-right: 20px;
                }
            </style>';

            echo '<div class="wpturbo-checkout-fields-wrapper">';
            foreach ($fields as $section => $field_group) {
                // Skip the account fields section
                if ($section === "account") {
                    continue;
                }
                echo "<h2>" . ucfirst($section) . " Fields</h2>";
                foreach ($field_group as $key => $field) {
                    // The checkbox is checked if the option is set to "1", meaning the field is disabled.
                    $is_disabled =
                        isset($options[$key]) && $options[$key] === "1";
                    $checked = checked($is_disabled, true, false);
                    echo "<label>";
                    echo '<input type="checkbox" name="wpturbo_checkout_fields_options[' .
                        esc_attr($key) .
                        ']" ' .
                        $checked .
                        ">";
                    echo esc_html($field["label"]);
                    echo "</label>";
                }
            }
            echo "</div>";

            submit_button(); ?>
        </form>
    </div>
<?php
}

// Override checkout fields based on saved options
add_filter(
    "woocommerce_checkout_fields",
    "wpturbo_custom_override_checkout_fields"
);
function wpturbo_custom_override_checkout_fields($fields)
{
    if (is_admin()) {
        // If we are in the admin area, do not modify the fields
        return $fields;
    }

    $options = get_option("wpturbo_checkout_fields_options", []);

    foreach ($fields as $section => $field_group) {
        foreach ($field_group as $key => $field) {
            // If the option is set to "1", the field should be unset (hidden) on the front-end.
            if (!empty($options[$key])) {
                unset($fields[$section][$key]);
            }
        }
    }
    return $fields;
}




// kh bc 


function supplierX_ajax_kh()
{
    if (isset($_POST['product_id']) && isset($_POST['variations'])) {

        // $product_id = sanitize_text_field($_POST['product_id']);

        // Decode the variations JSON string
        $variations_data = json_decode(stripslashes($_POST['variations']), true);



        // echo "<pre>";
        // print_r($variations_data);


        if ($variations_data !== null) {

            foreach ($variations_data as $variation) {
                if (class_exists('WooCommerce')) {
                    $prod_id = $variation['product_id'];
                    $quantity = $variation['quantity'];
                    $variation_id = $variation['variation_id'];

                    if (!empty($quantity)) {
                        WC()->cart->add_to_cart($prod_id, $quantity, $variation_id);
                    }
                }
            }
        }
    }

    wp_die();
}

add_action('wp_ajax_supplierX_ajax_kh', 'supplierX_ajax_kh');
add_action('wp_ajax_nopriv_supplierX_ajax_kh', 'supplierX_ajax_kh');







function suplierX_add_code_to_footer()
{

?>
    <script>
        jQuery(document).ready(function($) {
            $('#addToCartButton_x').on('click', function() {

                var ajax_url = jQuery(this).attr("ajax_url");

                var productID = $('input[name="product_id"]').val();

                // alert('Product ID: ' + productID);

                var variationsData = [];

                $('tr').each(function() {
                    var variationId = $(this).find('td:eq(2)').text();
                    var quantity = $(this).find('.variation-quantity').val();


                    if (variationId.trim() !== "" && quantity.trim() !== "") {
                        variationsData.push({
                            product_id: productID,
                            variation_id: variationId,
                            quantity: quantity
                        });
                    }
                });

                // alert(JSON.stringify(variationsData));
                // alert('Button clicked!');
                // alert(variationsData);

                $.ajax({
                    type: 'POST',
                    url: ajax_url,
                    data: {
                        action: 'supplierX_ajax_kh',
                        product_id: productID,
                        variations: JSON.stringify(variationsData)
                    },
                    success: function(response) {
                        console.log(response);
                    },
                    error: function(xhr, textStatus, errorThrown) {
                        console.error('Error:', textStatus, errorThrown);
                    }
                });
            });
        });


        jQuery(document).ready(function($) {
            $("#variance_supplierx").click(function() {
                $(".product_variance_supplierx").slideToggle(800);
            });
        });
    </script>

    <style>
        a#addToCartButton_x {
            border: 1px solid;
            padding: 10px 7px;
            max-width: 85px;
            text-align: center;
            border-radius: 30px;
            font-size: 12px;
            transition: background-color 1s, color 1s;
        }

        a#addToCartButton_x:hover {
            background-color: black;
            color: white;
        }

        .product_variations_supplierx {
            margin-bottom: 8px;
        }

        #variance_supplierx {
            border: 1px solid;
            max-width: 130px;
            text-align: center;
            font-size: 13px;
            padding: 5px 2px;
            border-radius: 10px;
            cursor: pointer;

            transition: background-color .5s, color .5s;
        }

        #variance_supplierx:hover {
            background-color: black;
            color: white;
        }
    </style>


<?php

}
add_action('wp_footer', 'suplierX_add_code_to_footer');
