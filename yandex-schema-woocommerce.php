<?php
/**
 * Plugin Name: Yandex Schema.org for WooCommerce
 * Plugin URI: https://uralgips-izhevsk.ru
 * Description: Генерирует микроразметку schema.org для WooCommerce согласно требованиям Яндекса
 * Version: 2.5.0
 * Author: UralGips
 * Author URI: https://uralgips-izhevsk.ru
 * Text Domain: yandex-schema-woocommerce
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Check if WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}

/**
 * Main Plugin Class
 */
class Yandex_Schema_WooCommerce {

    /**
     * Instance
     */
    private static $instance = null;

    /**
     * Organization data
     */
    private $organization = array();

    /**
     * Options
     */
    private $options = array();

    /**
     * Branches (филиалы)
     */
    private $branches = array();

    /**
     * Cache group
     */
    private $cache_group = 'yandex_schema';

    /**
     * Get instance
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Load options from database
        $this->load_options();
        
        // Load branches
        $this->load_branches();

        // Set organization data from options
        $this->organization = array(
            'name' => $this->options['org_name'] ?: get_bloginfo( 'name' ),
            'url' => home_url(),
            'logo' => $this->options['org_logo'] ?: get_template_directory_uri() . '/assets/images/logo.png',
            'telephone' => $this->options['org_phone'] ?: '+7 (3412) 00-00-00',
            'email' => $this->options['org_email'] ?: 'info@uralgips.ru',
            'address' => array(
                'country' => 'RU',
                'region' => $this->options['org_region'] ?: 'Удмуртская Республика',
                'city' => $this->options['org_city'] ?: 'Ижевск',
                'street' => $this->options['org_street'] ?: 'ул. Пушкинская, 268',
                'postal' => $this->options['org_postal'] ?: '426000'
            ),
            'geo' => array(
                'latitude' => $this->options['org_lat'] ?: '56.8431',
                'longitude' => $this->options['org_lng'] ?: '53.2048'
            ),
            'opening_hours' => array(
                'Mo-Fr 08:00-18:00',
                'Sa 09:00-15:00'
            ),
            'delivery' => array(
                'price' => $this->options['delivery_price'] ?: 0,
                'free_from' => $this->options['delivery_free_from'] ?: 5000,
                'time' => $this->options['delivery_time'] ?: '1-2 дня'
            )
        );

        // Disable WooCommerce default structured data
        add_action( 'init', array( $this, 'disable_woocommerce_schema' ) );

        // Add custom schema.org markup
        add_action( 'wp_head', array( $this, 'output_schema' ), 5 );

        // Admin settings
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // Add product meta fields
        add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_product_meta_fields' ) );
        add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_meta_fields' ) );

        // Clear cache on product save
        add_action( 'woocommerce_update_product', array( $this, 'clear_product_cache' ) );
        add_action( 'update_option_yandex_schema_options', array( $this, 'clear_all_cache' ) );
        add_action( 'update_option_yandex_schema_branches', array( $this, 'clear_all_cache' ) );
    }

    /**
     * Load options from database
     */
    private function load_options() {
        $defaults = array(
            'org_name' => '',
            'org_phone' => '',
            'org_email' => '',
            'org_logo' => '',
            'org_region' => 'Удмуртская Республика',
            'org_city' => 'Ижевск',
            'org_street' => '',
            'org_postal' => '',
            'org_lat' => '',
            'org_lng' => '',
            'org_description' => '',
            'delivery_price' => 500,
            'delivery_free_from' => 5000,
            'delivery_time' => '1-2 дня',
            'enable_cache' => true,
            'cache_time' => 3600,
            'excluded_attributes' => array(),
            'disable_breadcrumbs' => false,
            'disable_product' => false,
            'disable_organization' => false,
            'disable_website' => false,
            'disable_local_business' => false,
            'disable_catalog' => false,
        );
        $this->options = wp_parse_args( get_option( 'yandex_schema_options', array() ), $defaults );
    }

    /**
     * Load branches from database
     */
    private function load_branches() {
        $this->branches = get_option( 'yandex_schema_branches', array() );
        
        if ( ! is_array( $this->branches ) ) {
            $this->branches = array();
        }
    }

    /**
     * Disable WooCommerce default schema
     */
    public function disable_woocommerce_schema() {
        // Remove WooCommerce structured data generation hooks
        remove_action( 'woocommerce_single_product_summary', array( WC()->structured_data, 'generate_product_data' ), 60 );
        remove_action( 'woocommerce_shop_loop', array( WC()->structured_data, 'generate_product_data' ), 10 );
        
        // Disable all WooCommerce structured data via filters
        add_filter( 'woocommerce_structured_data_product', '__return_empty_array' );
        add_filter( 'woocommerce_structured_data_type_for_page', '__return_empty_array' );
        add_filter( 'woocommerce_structured_data_product_offer', '__return_empty_array' );
        add_filter( 'woocommerce_structured_data_review', '__return_empty_array' );
        add_filter( 'woocommerce_structured_data_breadcrumblist', '__return_empty_array' );
        
        // Remove JSON-LD output from footer
        remove_action( 'wp_footer', array( WC()->structured_data, 'output_structured_data' ), 10 );
        
        // Disable WooCommerce Open Graph meta tags
        add_filter( 'woocommerce_open_graph_tags', '__return_empty_array' );
        remove_action( 'wp_head', 'wc_open_graph_tags', 10 );
    }

    /**
     * Output schema.org markup
     */
    public function output_schema() {
        $schema = array();

        // Organization (if not disabled)
        if ( empty( $this->options['disable_organization'] ) ) {
            $schema[] = $this->get_organization_schema();
        }

        // WebSite (if not disabled)
        if ( empty( $this->options['disable_website'] ) ) {
            $schema[] = $this->get_website_schema();
        }

        // BreadcrumbList (if not disabled)
        if ( ! is_front_page() && empty( $this->options['disable_breadcrumbs'] ) ) {
            $breadcrumb = $this->get_breadcrumb_schema();
            if ( $breadcrumb ) {
                $schema[] = $breadcrumb;
            }
        }

        // Product page (if not disabled)
        if ( is_product() && empty( $this->options['disable_product'] ) ) {
            $schema[] = $this->get_product_schema();
        }

        // Shop/Category page - OfferCatalog (if not disabled)
        if ( ( is_shop() || is_product_category() || is_product_tag() ) && empty( $this->options['disable_catalog'] ) ) {
            $schema[] = $this->get_catalog_schema();
        }

        // Front page - LocalBusiness + Branches (if not disabled)
        if ( is_front_page() && empty( $this->options['disable_local_business'] ) ) {
            $schema[] = $this->get_local_business_schema();
            
            // Add branches as separate LocalBusiness entities
            $branches_schemas = $this->get_branches_schema();
            foreach ( $branches_schemas as $branch_schema ) {
                $schema[] = $branch_schema;
            }
        }

        // Output all schemas
        foreach ( $schema as $item ) {
            if ( ! empty( $item ) ) {
                echo '<script type="application/ld+json">' . "\n";
                echo wp_json_encode( $item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
                echo "\n</script>\n";
            }
        }
    }

    /**
     * Get Organization schema
     */
    private function get_organization_schema() {
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            '@id' => home_url( '#organization' ),
            'name' => $this->organization['name'],
            'url' => $this->organization['url'],
            'logo' => array(
                '@type' => 'ImageObject',
                'url' => $this->organization['logo']
            ),
            'contactPoint' => array(
                '@type' => 'ContactPoint',
                'telephone' => $this->organization['telephone'],
                'contactType' => 'sales',
                'availableLanguage' => 'Russian'
            ),
            'address' => array(
                '@type' => 'PostalAddress',
                'addressCountry' => $this->organization['address']['country'],
                'addressRegion' => $this->organization['address']['region'],
                'addressLocality' => $this->organization['address']['city'],
                'streetAddress' => $this->organization['address']['street'],
                'postalCode' => $this->organization['address']['postal']
            )
        );
        
        // Add branches count if available
        if ( ! empty( $this->branches ) ) {
            $schema['numberOfEmployees'] = array(
                '@type' => 'QuantitativeValue',
                'name' => 'Количество филиалов',
                'value' => count( $this->branches )
            );
        }
        
        return $schema;
    }

    /**
     * Get WebSite schema
     */
    private function get_website_schema() {
        return array(
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $this->organization['name'],
            'url' => home_url(),
            'potentialAction' => array(
                '@type' => 'SearchAction',
                'target' => home_url( '/?s={search_term_string}' ),
                'query-input' => array(
                    '@type' => 'PropertyValueSpecification',
                    'valueRequired' => true,
                    'valueName' => 'search_term_string'
                )
            )
        );
    }

    /**
     * Get LocalBusiness schema for front page
     */
    private function get_local_business_schema() {
        return array(
            '@context' => 'https://schema.org',
            '@type' => 'Store',
            '@id' => home_url( '#main-store' ),
            'name' => $this->organization['name'],
            'description' => 'Интернет-магазин строительных материалов в Ижевске. Гипсовые смеси, штукатурки, шпаклевки от УралГипс.',
            'url' => home_url(),
            'image' => $this->organization['logo'],
            'telephone' => $this->organization['telephone'],
            'email' => $this->organization['email'],
            'priceRange' => '₽₽',
            'address' => array(
                '@type' => 'PostalAddress',
                'addressCountry' => $this->organization['address']['country'],
                'addressRegion' => $this->organization['address']['region'],
                'addressLocality' => $this->organization['address']['city'],
                'streetAddress' => $this->organization['address']['street'],
                'postalCode' => $this->organization['address']['postal']
            ),
            'geo' => array(
                '@type' => 'GeoCoordinates',
                'latitude' => $this->organization['geo']['latitude'],
                'longitude' => $this->organization['geo']['longitude']
            ),
            'openingHoursSpecification' => array(
                array(
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday' ),
                    'opens' => '08:00',
                    'closes' => '18:00'
                ),
                array(
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => 'Saturday',
                    'opens' => '09:00',
                    'closes' => '15:00'
                )
            ),
            'paymentAccepted' => array( 'Cash', 'Credit Card', 'Bank Transfer' ),
            'currenciesAccepted' => 'RUB'
        );
    }

    /**
     * Get Branches schema (multiple LocalBusiness with branchOf)
     */
    private function get_branches_schema() {
        if ( empty( $this->branches ) ) {
            return array();
        }

        $schemas = array();
        
        // Parent organization reference
        $parent_org = array(
            '@type' => 'Organization',
            '@id' => home_url( '#organization' ),
            'name' => $this->organization['name'],
            'url' => home_url()
        );

        foreach ( $this->branches as $index => $branch ) {
            $branch_id = home_url( '#branch-' . ( $index + 1 ) );
            
            $opening_hours = array();
            
            // Weekday hours
            if ( ! empty( $branch['hours_weekday_open'] ) && ! empty( $branch['hours_weekday_close'] ) ) {
                $opening_hours[] = array(
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday' ),
                    'opens' => $branch['hours_weekday_open'],
                    'closes' => $branch['hours_weekday_close']
                );
            }
            
            // Saturday hours
            if ( ! empty( $branch['hours_saturday_open'] ) && ! empty( $branch['hours_saturday_close'] ) ) {
                $opening_hours[] = array(
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => 'Saturday',
                    'opens' => $branch['hours_saturday_open'],
                    'closes' => $branch['hours_saturday_close']
                );
            }

            $branch_schema = array(
                '@context' => 'https://schema.org',
                '@type' => 'Store',
                '@id' => $branch_id,
                'name' => $branch['name'],
                'branchOf' => $parent_org,
                'image' => $this->organization['logo'],
                'priceRange' => '₽₽',
                'address' => array(
                    '@type' => 'PostalAddress',
                    'addressCountry' => 'RU',
                    'addressRegion' => $branch['region'] ?: $this->organization['address']['region'],
                    'addressLocality' => $branch['city'] ?: $this->organization['address']['city'],
                    'streetAddress' => $branch['street'],
                    'postalCode' => $branch['postal']
                ),
                'paymentAccepted' => array( 'Cash', 'Credit Card', 'Bank Transfer' ),
                'currenciesAccepted' => 'RUB'
            );

            // Add phone if available
            if ( ! empty( $branch['phone'] ) ) {
                $branch_schema['telephone'] = $branch['phone'];
            }

            // Add email if available
            if ( ! empty( $branch['email'] ) ) {
                $branch_schema['email'] = $branch['email'];
            }

            // Add geo coordinates if available
            if ( ! empty( $branch['lat'] ) && ! empty( $branch['lng'] ) ) {
                $branch_schema['geo'] = array(
                    '@type' => 'GeoCoordinates',
                    'latitude' => $branch['lat'],
                    'longitude' => $branch['lng']
                );
            }

            // Add opening hours if available
            if ( ! empty( $opening_hours ) ) {
                $branch_schema['openingHoursSpecification'] = $opening_hours;
            }

            $schemas[] = $branch_schema;
        }

        return $schemas;
    }

    /**
     * Get Product schema (Yandex requirements)
     */
    private function get_product_schema() {
        global $product;

        // Ensure $product is a WC_Product object
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            $product = wc_get_product( get_the_ID() );
        }

        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            return array();
        }

        // Get product data
        $product_name = $product->get_name();
        $product_description = wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() );
        $product_url = get_permalink( $product->get_id() );
        $product_sku = $product->get_sku();

        // Get images
        $images = array();
        $main_image = wp_get_attachment_image_url( $product->get_image_id(), 'full' );
        if ( $main_image ) {
            $images[] = $main_image;
        }
        
        $gallery_ids = $product->get_gallery_image_ids();
        foreach ( $gallery_ids as $gallery_id ) {
            $gallery_image = wp_get_attachment_image_url( $gallery_id, 'full' );
            if ( $gallery_image ) {
                $images[] = $gallery_image;
            }
        }

        // Price data
        $price = $product->get_price();
        $regular_price = $product->get_regular_price();
        $sale_price = $product->get_sale_price();

        // Availability (Yandex requirement)
        $stock_status = $product->get_stock_status();
        $availability_map = array(
            'instock' => 'https://schema.org/InStock',
            'outofstock' => 'https://schema.org/OutOfStock',
            'onbackorder' => 'https://schema.org/PreOrder'
        );
        $availability = isset( $availability_map[ $stock_status ] ) 
            ? $availability_map[ $stock_status ] 
            : 'https://schema.org/InStock';

        // Build offers - check if variable product
        if ( $product->is_type( 'variable' ) ) {
            $offers = $this->get_variable_product_offers( $product );
            if ( ! $offers ) {
                // Fallback to simple offer
                $offers = array(
                    '@type' => 'Offer',
                    'url' => $product_url,
                    'price' => (float) $price,
                    'priceCurrency' => 'RUB',
                    'availability' => $availability
                );
            }
        } else {
            $offers = array(
                '@type' => 'Offer',
                'url' => $product_url,
                'price' => (float) $price,
                'priceCurrency' => 'RUB',
                'availability' => $availability,
                'seller' => array(
                    '@type' => 'Organization',
                    'name' => $this->organization['name']
                )
            );

            // Add priceValidUntil if on sale
            if ( $sale_price && $product->get_date_on_sale_to() ) {
                $offers['priceValidUntil'] = $product->get_date_on_sale_to()->date( 'Y-m-d' );
            }

            // Add shipping details
            $offers['shippingDetails'] = $this->get_shipping_details();
        }

        // Build product schema (Yandex requirements)
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            '@id' => $product_url . '#product',
            'name' => $product_name,
            'description' => $product_description,
            'url' => $product_url,
            'image' => count( $images ) === 1 ? $images[0] : $images,
            'offers' => $offers
        );

        // Add SKU if available
        if ( $product_sku ) {
            $schema['sku'] = $product_sku;
        }

        // Add brand if available (check meta field first, then taxonomy, then attributes)
        $brand = get_post_meta( $product->get_id(), '_brand', true );
        if ( ! $brand ) {
            $brands = wp_get_post_terms( $product->get_id(), 'product_brand', array( 'fields' => 'names' ) );
            if ( ! is_wp_error( $brands ) && ! empty( $brands ) ) {
                $brand = $brands[0];
            }
        }
        // Also check product attributes for brand (Производитель, Торговая марка, etc.)
        if ( ! $brand ) {
            $brand_attr_names = array( 'Производитель', 'Торговая марка', 'Brand', 'Бренд', 'Manufacturer' );
            foreach ( $product->get_attributes() as $attribute ) {
                $attr_label = wc_attribute_label( $attribute->get_name(), $product );
                if ( in_array( $attr_label, $brand_attr_names, true ) ) {
                    if ( $attribute->is_taxonomy() ) {
                        $terms = wc_get_product_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'names' ) );
                        if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                            $brand = $terms[0];
                            break;
                        }
                    } else {
                        $options = $attribute->get_options();
                        if ( ! empty( $options ) ) {
                            $brand = $options[0];
                            break;
                        }
                    }
                }
            }
        }
        if ( $brand ) {
            $schema['brand'] = array(
                '@type' => 'Brand',
                'name' => $brand
            );
        }

        // Add GTIN if available (from product meta)
        $gtin = get_post_meta( $product->get_id(), '_gtin', true );
        if ( $gtin ) {
            $schema['gtin'] = $gtin;
        }

        // Add MPN if available
        $mpn = get_post_meta( $product->get_id(), '_mpn', true );
        if ( $mpn ) {
            $schema['mpn'] = $mpn;
        }

        // Add category
        $categories = get_the_terms( $product->get_id(), 'product_cat' );
        if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
            $schema['category'] = $categories[0]->name;
        }

        // Add reviews/ratings if available
        if ( $product->get_review_count() > 0 ) {
            $schema['aggregateRating'] = array(
                '@type' => 'AggregateRating',
                'ratingValue' => $product->get_average_rating(),
                'reviewCount' => $product->get_review_count(),
                'bestRating' => '5',
                'worstRating' => '1'
            );
        }

        // Add weight if available
        if ( $product->has_weight() ) {
            $schema['weight'] = array(
                '@type' => 'QuantitativeValue',
                'value' => $product->get_weight(),
                'unitCode' => 'KGM'
            );
        }

        // Add dimensions if available
        if ( $product->has_dimensions() ) {
            $schema['depth'] = array(
                '@type' => 'QuantitativeValue',
                'value' => $product->get_length(),
                'unitCode' => 'CMT'
            );
            $schema['width'] = array(
                '@type' => 'QuantitativeValue',
                'value' => $product->get_width(),
                'unitCode' => 'CMT'
            );
            $schema['height'] = array(
                '@type' => 'QuantitativeValue',
                'value' => $product->get_height(),
                'unitCode' => 'CMT'
            );
        }

        // Add individual reviews
        $reviews = $this->get_product_reviews( $product->get_id() );
        if ( ! empty( $reviews ) ) {
            $schema['review'] = $reviews;
        }

        // Add product attributes/specifications (additionalProperty)
        $attributes = $this->get_product_attributes( $product );
        if ( ! empty( $attributes ) ) {
            $schema['additionalProperty'] = $attributes;
        }

        // Add special schema properties for common attributes
        $this->add_special_attributes( $schema, $product );

        return $schema;
    }

    /**
     * Get product attributes as PropertyValue array
     */
    private function get_product_attributes( $product ) {
        $attributes = $product->get_attributes();
        
        if ( empty( $attributes ) ) {
            return array();
        }

        $excluded = $this->options['excluded_attributes'] ?? array();
        $property_values = array();

        foreach ( $attributes as $attribute ) {
            // Skip attributes used for variations only
            if ( $attribute->get_variation() && $product->is_type( 'variable' ) ) {
                continue;
            }
            
            // Skip excluded attributes
            $attr_slug = $attribute->get_name();
            if ( in_array( $attr_slug, $excluded, true ) ) {
                continue;
            }

            $name = wc_attribute_label( $attribute->get_name(), $product );
            $values = array();

            if ( $attribute->is_taxonomy() ) {
                // Taxonomy attribute
                $terms = wc_get_product_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'names' ) );
                $values = $terms;
            } else {
                // Custom attribute
                $values = $attribute->get_options();
            }

            if ( ! empty( $values ) ) {
                $value_string = implode( ', ', $values );
                
                $property_values[] = array(
                    '@type' => 'PropertyValue',
                    'name' => $name,
                    'value' => $value_string
                );
            }
        }

        return $property_values;
    }

    /**
     * Add special schema properties for common attributes (color, material, etc.)
     */
    private function add_special_attributes( &$schema, $product ) {
        $attributes = $product->get_attributes();
        $excluded = $this->options['excluded_attributes'] ?? array();
        
        // Map of WooCommerce attribute slugs to schema.org properties
        $special_map = array(
            'pa_color' => 'color',
            'pa_colour' => 'color',
            'pa_cvet' => 'color',
            'pa_material' => 'material',
            'pa_size' => 'size',
            'pa_razmer' => 'size',
            'pa_model' => 'model',
            'pa_pattern' => 'pattern'
        );

        foreach ( $attributes as $attr_name => $attribute ) {
            $attr_slug = $attribute->get_name();
            
            // Skip excluded attributes
            if ( in_array( $attr_slug, $excluded, true ) ) {
                continue;
            }
            
            // Check if this attribute maps to a special schema property
            if ( isset( $special_map[ $attr_slug ] ) ) {
                $schema_property = $special_map[ $attr_slug ];
                
                if ( $attribute->is_taxonomy() ) {
                    $terms = wc_get_product_terms( $product->get_id(), $attr_slug, array( 'fields' => 'names' ) );
                    if ( ! empty( $terms ) ) {
                        $schema[ $schema_property ] = count( $terms ) === 1 ? $terms[0] : implode( ', ', $terms );
                    }
                } else {
                    $options = $attribute->get_options();
                    if ( ! empty( $options ) ) {
                        $schema[ $schema_property ] = count( $options ) === 1 ? $options[0] : implode( ', ', $options );
                    }
                }
            }
        }

        // Also check custom meta fields for common properties
        $custom_fields = array(
            '_color' => 'color',
            '_material' => 'material',
            '_model' => 'model'
        );

        foreach ( $custom_fields as $meta_key => $schema_property ) {
            if ( ! isset( $schema[ $schema_property ] ) ) {
                $value = get_post_meta( $product->get_id(), $meta_key, true );
                if ( $value ) {
                    $schema[ $schema_property ] = $value;
                }
            }
        }
    }

    /**
     * Get Product schema for variable products (AggregateOffer)
     */
    private function get_variable_product_offers( $product ) {
        $variations = $product->get_available_variations();
        
        if ( empty( $variations ) ) {
            return null;
        }

        $prices = array();
        $offers = array();

        foreach ( $variations as $variation ) {
            $variation_obj = wc_get_product( $variation['variation_id'] );
            if ( ! $variation_obj ) continue;

            $price = $variation_obj->get_price();
            if ( $price ) {
                $prices[] = (float) $price;
            }

            $stock_status = $variation_obj->get_stock_status();
            $availability_map = array(
                'instock' => 'https://schema.org/InStock',
                'outofstock' => 'https://schema.org/OutOfStock',
                'onbackorder' => 'https://schema.org/PreOrder'
            );

            $offers[] = array(
                '@type' => 'Offer',
                'url' => get_permalink( $product->get_id() ),
                'price' => (float) $price,
                'priceCurrency' => 'RUB',
                'availability' => $availability_map[ $stock_status ] ?? 'https://schema.org/InStock',
                'sku' => $variation_obj->get_sku(),
                'name' => $variation_obj->get_name()
            );
        }

        if ( empty( $prices ) ) {
            return null;
        }

        return array(
            '@type' => 'AggregateOffer',
            'lowPrice' => min( $prices ),
            'highPrice' => max( $prices ),
            'priceCurrency' => 'RUB',
            'offerCount' => count( $offers ),
            'offers' => $offers
        );
    }

    /**
     * Get product reviews for schema
     */
    private function get_product_reviews( $product_id, $limit = 5 ) {
        $reviews = get_comments( array(
            'post_id' => $product_id,
            'status' => 'approve',
            'type' => 'review',
            'number' => $limit
        ) );

        if ( empty( $reviews ) ) {
            return array();
        }

        $schema_reviews = array();

        foreach ( $reviews as $review ) {
            $rating = get_comment_meta( $review->comment_ID, 'rating', true );
            
            $schema_reviews[] = array(
                '@type' => 'Review',
                'author' => array(
                    '@type' => 'Person',
                    'name' => $review->comment_author
                ),
                'datePublished' => date( 'Y-m-d', strtotime( $review->comment_date ) ),
                'reviewBody' => wp_strip_all_tags( $review->comment_content ),
                'reviewRating' => array(
                    '@type' => 'Rating',
                    'ratingValue' => $rating ?: 5,
                    'bestRating' => '5',
                    'worstRating' => '1'
                )
            );
        }

        return $schema_reviews;
    }

    /**
     * Add shipping details to offer
     */
    private function get_shipping_details() {
        return array(
            '@type' => 'OfferShippingDetails',
            'shippingRate' => array(
                '@type' => 'MonetaryAmount',
                'value' => $this->organization['delivery']['price'],
                'currency' => 'RUB'
            ),
            'shippingDestination' => array(
                '@type' => 'DefinedRegion',
                'addressCountry' => 'RU',
                'addressRegion' => $this->organization['address']['region']
            ),
            'deliveryTime' => array(
                '@type' => 'ShippingDeliveryTime',
                'handlingTime' => array(
                    '@type' => 'QuantitativeValue',
                    'minValue' => 0,
                    'maxValue' => 1,
                    'unitCode' => 'DAY'
                ),
                'transitTime' => array(
                    '@type' => 'QuantitativeValue',
                    'minValue' => 1,
                    'maxValue' => 2,
                    'unitCode' => 'DAY'
                )
            )
        );
    }

    /**
     * Get OfferCatalog schema for shop/category pages (Yandex requirement)
     */
    private function get_catalog_schema() {
        global $wp_query;

        // Get catalog info
        $catalog_name = 'Каталог';
        $catalog_description = 'Строительные материалы УралГипс';
        $catalog_image = $this->organization['logo'];

        if ( is_product_category() ) {
            $term = get_queried_object();
            $catalog_name = $term->name;
            $catalog_description = $term->description ?: 'Товары категории ' . $term->name;
            
            // Get category image
            $thumbnail_id = get_term_meta( $term->term_id, 'thumbnail_id', true );
            if ( $thumbnail_id ) {
                $catalog_image = wp_get_attachment_image_url( $thumbnail_id, 'full' );
            }
        } elseif ( is_product_tag() ) {
            $term = get_queried_object();
            $catalog_name = $term->name;
            $catalog_description = 'Товары с меткой ' . $term->name;
        }

        // Get products
        $products_list = array();
        $counter = 1;

        if ( have_posts() ) {
            while ( have_posts() ) {
                the_post();
                global $product;

                if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
                    continue;
                }

                $product_description = wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() );
                if ( strlen( $product_description ) > 200 ) {
                    $product_description = mb_substr( $product_description, 0, 197 ) . '...';
                }

                $stock_status = $product->get_stock_status();
                $availability_map = array(
                    'instock' => 'https://schema.org/InStock',
                    'outofstock' => 'https://schema.org/OutOfStock',
                    'onbackorder' => 'https://schema.org/PreOrder'
                );
                $availability = isset( $availability_map[ $stock_status ] ) 
                    ? $availability_map[ $stock_status ] 
                    : 'https://schema.org/InStock';

                $offer = array(
                    '@type' => 'Offer',
                    'position' => $counter,
                    'url' => get_permalink( $product->get_id() ),
                    'name' => $product->get_name(),
                    'description' => $product_description,
                    'image' => wp_get_attachment_image_url( $product->get_image_id(), 'full' ) ?: '',
                    'price' => (float) $product->get_price(),
                    'priceCurrency' => 'RUB',
                    'availability' => $availability
                );

                $products_list[] = $offer;
                $counter++;
            }
            wp_reset_postdata();
        }

        // Rewind posts for normal loop
        rewind_posts();

        // Build OfferCatalog schema (Yandex requirement)
        return array(
            '@context' => 'https://schema.org',
            '@type' => 'OfferCatalog',
            'name' => $catalog_name,
            'description' => $catalog_description,
            'image' => $catalog_image,
            'numberOfItems' => count( $products_list ),
            'itemListElement' => $products_list
        );
    }

    /**
     * Get BreadcrumbList schema
     */
    private function get_breadcrumb_schema() {
        $breadcrumbs = array();
        $position = 1;

        // Home
        $breadcrumbs[] = array(
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => 'Главная',
            'item' => home_url()
        );

        // Shop
        if ( is_woocommerce() ) {
            $shop_page_id = wc_get_page_id( 'shop' );
            if ( $shop_page_id ) {
                $breadcrumbs[] = array(
                    '@type' => 'ListItem',
                    'position' => $position++,
                    'name' => 'Каталог',
                    'item' => get_permalink( $shop_page_id )
                );
            }
        }

        // Category
        if ( is_product_category() ) {
            $term = get_queried_object();
            
            // Parent categories
            $ancestors = get_ancestors( $term->term_id, 'product_cat' );
            $ancestors = array_reverse( $ancestors );
            
            foreach ( $ancestors as $ancestor_id ) {
                $ancestor = get_term( $ancestor_id, 'product_cat' );
                $breadcrumbs[] = array(
                    '@type' => 'ListItem',
                    'position' => $position++,
                    'name' => $ancestor->name,
                    'item' => get_term_link( $ancestor )
                );
            }

            // Current category
            $breadcrumbs[] = array(
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $term->name,
                'item' => get_term_link( $term )
            );
        }

        // Single product
        if ( is_product() ) {
            global $product;
            
            // Ensure $product is a WC_Product object
            if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
                $product = wc_get_product( get_the_ID() );
            }
            
            if ( ! $product ) {
                return array(
                    '@context' => 'https://schema.org',
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => $breadcrumbs
                );
            }

            // Get product categories
            $categories = get_the_terms( $product->get_id(), 'product_cat' );
            if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
                // Find deepest category
                $deepest_cat = null;
                $max_depth = -1;

                foreach ( $categories as $cat ) {
                    $depth = count( get_ancestors( $cat->term_id, 'product_cat' ) );
                    if ( $depth > $max_depth ) {
                        $max_depth = $depth;
                        $deepest_cat = $cat;
                    }
                }

                if ( $deepest_cat ) {
                    // Add parent categories
                    $ancestors = get_ancestors( $deepest_cat->term_id, 'product_cat' );
                    $ancestors = array_reverse( $ancestors );
                    
                    foreach ( $ancestors as $ancestor_id ) {
                        $ancestor = get_term( $ancestor_id, 'product_cat' );
                        $breadcrumbs[] = array(
                            '@type' => 'ListItem',
                            'position' => $position++,
                            'name' => $ancestor->name,
                            'item' => get_term_link( $ancestor )
                        );
                    }

                    // Add category
                    $breadcrumbs[] = array(
                        '@type' => 'ListItem',
                        'position' => $position++,
                        'name' => $deepest_cat->name,
                        'item' => get_term_link( $deepest_cat )
                    );
                }
            }

            // Add product (without item for current page)
            $breadcrumbs[] = array(
                '@type' => 'ListItem',
                'position' => $position,
                'name' => $product->get_name()
            );
        }

        // Product tag
        if ( is_product_tag() ) {
            $term = get_queried_object();
            $breadcrumbs[] = array(
                '@type' => 'ListItem',
                'position' => $position,
                'name' => $term->name
            );
        }

        return array(
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $breadcrumbs
        );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            'Yandex Schema.org',
            'Yandex Schema',
            'manage_options',
            'yandex-schema-woocommerce',
            array( $this, 'settings_page' )
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting( 'yandex_schema_settings', 'yandex_schema_options', array(
            'sanitize_callback' => array( $this, 'sanitize_options' )
        ) );
        
        register_setting( 'yandex_schema_settings', 'yandex_schema_branches', array(
            'sanitize_callback' => array( $this, 'sanitize_branches' )
        ) );
    }

    /**
     * Sanitize options
     */
    public function sanitize_options( $input ) {
        $sanitized = array();
        
        $text_fields = array( 'org_name', 'org_phone', 'org_email', 'org_logo', 'org_region', 
                              'org_city', 'org_street', 'org_postal', 'org_lat', 'org_lng', 
                              'org_description', 'delivery_time' );
        
        foreach ( $text_fields as $field ) {
            $sanitized[ $field ] = isset( $input[ $field ] ) ? sanitize_text_field( $input[ $field ] ) : '';
        }
        
        $sanitized['delivery_price'] = isset( $input['delivery_price'] ) ? absint( $input['delivery_price'] ) : 0;
        $sanitized['delivery_free_from'] = isset( $input['delivery_free_from'] ) ? absint( $input['delivery_free_from'] ) : 5000;
        $sanitized['enable_cache'] = isset( $input['enable_cache'] ) ? (bool) $input['enable_cache'] : false;
        $sanitized['cache_time'] = isset( $input['cache_time'] ) ? absint( $input['cache_time'] ) : 3600;
        
        // Sanitize schema disable options
        $sanitized['disable_breadcrumbs'] = isset( $input['disable_breadcrumbs'] ) ? (bool) $input['disable_breadcrumbs'] : false;
        $sanitized['disable_product'] = isset( $input['disable_product'] ) ? (bool) $input['disable_product'] : false;
        $sanitized['disable_organization'] = isset( $input['disable_organization'] ) ? (bool) $input['disable_organization'] : false;
        $sanitized['disable_website'] = isset( $input['disable_website'] ) ? (bool) $input['disable_website'] : false;
        $sanitized['disable_local_business'] = isset( $input['disable_local_business'] ) ? (bool) $input['disable_local_business'] : false;
        $sanitized['disable_catalog'] = isset( $input['disable_catalog'] ) ? (bool) $input['disable_catalog'] : false;
        
        // Sanitize excluded attributes
        $sanitized['excluded_attributes'] = array();
        if ( isset( $input['excluded_attributes'] ) && is_array( $input['excluded_attributes'] ) ) {
            foreach ( $input['excluded_attributes'] as $attr ) {
                $sanitized['excluded_attributes'][] = sanitize_text_field( $attr );
            }
        }
        
        return $sanitized;
    }

    /**
     * Sanitize branches data
     */
    public function sanitize_branches( $input ) {
        if ( ! is_array( $input ) ) {
            return array();
        }
        
        $sanitized = array();
        
        foreach ( $input as $index => $branch ) {
            if ( empty( $branch['name'] ) ) {
                continue; // Пропускаем филиалы без названия
            }
            
            $sanitized[] = array(
                'name'        => sanitize_text_field( $branch['name'] ?? '' ),
                'phone'       => sanitize_text_field( $branch['phone'] ?? '' ),
                'email'       => sanitize_email( $branch['email'] ?? '' ),
                'region'      => sanitize_text_field( $branch['region'] ?? '' ),
                'city'        => sanitize_text_field( $branch['city'] ?? '' ),
                'street'      => sanitize_text_field( $branch['street'] ?? '' ),
                'postal'      => sanitize_text_field( $branch['postal'] ?? '' ),
                'lat'         => sanitize_text_field( $branch['lat'] ?? '' ),
                'lng'         => sanitize_text_field( $branch['lng'] ?? '' ),
                'hours_weekday_open'  => sanitize_text_field( $branch['hours_weekday_open'] ?? '08:00' ),
                'hours_weekday_close' => sanitize_text_field( $branch['hours_weekday_close'] ?? '18:00' ),
                'hours_saturday_open'  => sanitize_text_field( $branch['hours_saturday_open'] ?? '09:00' ),
                'hours_saturday_close' => sanitize_text_field( $branch['hours_saturday_close'] ?? '15:00' ),
                'hours_sunday_closed'  => isset( $branch['hours_sunday_closed'] ) ? (bool) $branch['hours_sunday_closed'] : true,
            );
        }
        
        return $sanitized;
    }

    /**
     * Add product meta fields (GTIN, MPN)
     */
    public function add_product_meta_fields() {
        echo '<div class="options_group">';
        echo '<h4 style="padding-left: 12px; margin-bottom: 5px;">Schema.org данные (для Яндекса)</h4>';
        
        woocommerce_wp_text_input( array(
            'id' => '_gtin',
            'label' => 'GTIN (штрих-код)',
            'placeholder' => 'Например: 4607001234567',
            'desc_tip' => true,
            'description' => 'Глобальный номер товара (EAN, UPC, ISBN)'
        ) );
        
        woocommerce_wp_text_input( array(
            'id' => '_mpn',
            'label' => 'MPN (артикул производителя)',
            'placeholder' => 'Например: ABC-12345',
            'desc_tip' => true,
            'description' => 'Код производителя товара'
        ) );
        
        woocommerce_wp_text_input( array(
            'id' => '_brand',
            'label' => 'Бренд',
            'placeholder' => 'Например: УралГипс',
            'desc_tip' => true,
            'description' => 'Бренд или производитель товара'
        ) );
        
        echo '</div>';
    }

    /**
     * Save product meta fields
     */
    public function save_product_meta_fields( $post_id ) {
        $fields = array( '_gtin', '_mpn', '_brand' );
        
        foreach ( $fields as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                update_post_meta( $post_id, $field, sanitize_text_field( $_POST[ $field ] ) );
            }
        }
    }

    /**
     * Clear product cache
     */
    public function clear_product_cache( $product_id ) {
        wp_cache_delete( 'product_schema_' . $product_id, $this->cache_group );
    }

    /**
     * Clear all cache
     */
    public function clear_all_cache() {
        wp_cache_flush();
    }

    /**
     * Settings page HTML
     */
    public function settings_page() {
        $options = $this->options;
        ?>
        <div class="wrap">
            <h1>Настройки Yandex Schema.org</h1>
            
            <div class="notice notice-info">
                <p><strong>Плагин генерирует:</strong> Product, Offer, AggregateOffer, OfferCatalog, Organization, LocalBusiness (+ филиалы с branchOf), WebSite, BreadcrumbList, Review</p>
            </div>

            <div class="notice notice-warning">
                <p><strong>Проверка:</strong> 
                    <a href="https://webmaster.yandex.ru/tools/microtest/" target="_blank">Валидатор Яндекса</a> | 
                    <a href="https://search.google.com/test/rich-results" target="_blank">Google Rich Results</a>
                </p>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields( 'yandex_schema_settings' ); ?>
                
                <h2>Данные организации</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="org_name">Название компании</label></th>
                        <td>
                            <input type="text" id="org_name" name="yandex_schema_options[org_name]" 
                                   value="<?php echo esc_attr( $options['org_name'] ); ?>" class="regular-text">
                            <p class="description">По умолчанию: <?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="org_phone">Телефон</label></th>
                        <td>
                            <input type="text" id="org_phone" name="yandex_schema_options[org_phone]" 
                                   value="<?php echo esc_attr( $options['org_phone'] ); ?>" class="regular-text"
                                   placeholder="+7 (XXX) XXX-XX-XX">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="org_email">Email</label></th>
                        <td>
                            <input type="email" id="org_email" name="yandex_schema_options[org_email]" 
                                   value="<?php echo esc_attr( $options['org_email'] ); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="org_logo">URL логотипа</label></th>
                        <td>
                            <input type="url" id="org_logo" name="yandex_schema_options[org_logo]" 
                                   value="<?php echo esc_attr( $options['org_logo'] ); ?>" class="large-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="org_description">Описание компании</label></th>
                        <td>
                            <textarea id="org_description" name="yandex_schema_options[org_description]" 
                                      rows="3" class="large-text"><?php echo esc_textarea( $options['org_description'] ); ?></textarea>
                        </td>
                    </tr>
                </table>

                <h2>Адрес</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="org_region">Регион</label></th>
                        <td>
                            <input type="text" id="org_region" name="yandex_schema_options[org_region]" 
                                   value="<?php echo esc_attr( $options['org_region'] ); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="org_city">Город</label></th>
                        <td>
                            <input type="text" id="org_city" name="yandex_schema_options[org_city]" 
                                   value="<?php echo esc_attr( $options['org_city'] ); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="org_street">Улица</label></th>
                        <td>
                            <input type="text" id="org_street" name="yandex_schema_options[org_street]" 
                                   value="<?php echo esc_attr( $options['org_street'] ); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="org_postal">Индекс</label></th>
                        <td>
                            <input type="text" id="org_postal" name="yandex_schema_options[org_postal]" 
                                   value="<?php echo esc_attr( $options['org_postal'] ); ?>" class="small-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label>Координаты</label></th>
                        <td>
                            <input type="text" id="org_lat" name="yandex_schema_options[org_lat]" 
                                   value="<?php echo esc_attr( $options['org_lat'] ); ?>" class="small-text" placeholder="Широта">
                            <input type="text" id="org_lng" name="yandex_schema_options[org_lng]" 
                                   value="<?php echo esc_attr( $options['org_lng'] ); ?>" class="small-text" placeholder="Долгота">
                        </td>
                    </tr>
                </table>

                <h2>Доставка</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="delivery_price">Стоимость доставки (₽)</label></th>
                        <td>
                            <input type="number" id="delivery_price" name="yandex_schema_options[delivery_price]" 
                                   value="<?php echo esc_attr( $options['delivery_price'] ); ?>" class="small-text" min="0">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="delivery_free_from">Бесплатно от (₽)</label></th>
                        <td>
                            <input type="number" id="delivery_free_from" name="yandex_schema_options[delivery_free_from]" 
                                   value="<?php echo esc_attr( $options['delivery_free_from'] ); ?>" class="small-text" min="0">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="delivery_time">Срок доставки</label></th>
                        <td>
                            <input type="text" id="delivery_time" name="yandex_schema_options[delivery_time]" 
                                   value="<?php echo esc_attr( $options['delivery_time'] ); ?>" class="regular-text"
                                   placeholder="1-2 дня">
                        </td>
                    </tr>
                </table>

                <h2>Отключение схем</h2>
                <p class="description">Отключите схемы, которые уже генерируются другими плагинами (Yoast SEO, RankMath, тема и т.д.).</p>
                <table class="form-table">
                    <tr>
                        <th>Product (товары)</th>
                        <td>
                            <label>
                                <input type="checkbox" name="yandex_schema_options[disable_product]" value="1" 
                                       <?php checked( $options['disable_product'] ?? false, true ); ?>>
                                Не генерировать разметку Product на страницах товаров
                            </label>
                            <p class="description">Отключите, если Yoast SEO или RankMath уже генерируют Product schema.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>BreadcrumbList</th>
                        <td>
                            <label>
                                <input type="checkbox" name="yandex_schema_options[disable_breadcrumbs]" value="1" 
                                       <?php checked( $options['disable_breadcrumbs'] ?? false, true ); ?>>
                                Не генерировать хлебные крошки
                            </label>
                            <p class="description">Отключите, если тема или SEO-плагин уже генерируют BreadcrumbList.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Organization</th>
                        <td>
                            <label>
                                <input type="checkbox" name="yandex_schema_options[disable_organization]" value="1" 
                                       <?php checked( $options['disable_organization'] ?? false, true ); ?>>
                                Не генерировать разметку организации
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>WebSite</th>
                        <td>
                            <label>
                                <input type="checkbox" name="yandex_schema_options[disable_website]" value="1" 
                                       <?php checked( $options['disable_website'] ?? false, true ); ?>>
                                Не генерировать разметку WebSite
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>LocalBusiness</th>
                        <td>
                            <label>
                                <input type="checkbox" name="yandex_schema_options[disable_local_business]" value="1" 
                                       <?php checked( $options['disable_local_business'] ?? false, true ); ?>>
                                Не генерировать LocalBusiness на главной (включая филиалы)
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>OfferCatalog</th>
                        <td>
                            <label>
                                <input type="checkbox" name="yandex_schema_options[disable_catalog]" value="1" 
                                       <?php checked( $options['disable_catalog'] ?? false, true ); ?>>
                                Не генерировать OfferCatalog на страницах категорий
                            </label>
                        </td>
                    </tr>
                </table>

                <h2>Кеширование</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="enable_cache">Включить кеш</label></th>
                        <td>
                            <input type="checkbox" id="enable_cache" name="yandex_schema_options[enable_cache]" 
                                   value="1" <?php checked( $options['enable_cache'], true ); ?>>
                            <label for="enable_cache">Кешировать schema.org разметку</label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="cache_time">Время кеша (сек)</label></th>
                        <td>
                            <input type="number" id="cache_time" name="yandex_schema_options[cache_time]" 
                                   value="<?php echo esc_attr( $options['cache_time'] ); ?>" class="small-text" min="60">
                        </td>
                    </tr>
                </table>

                <h2>Исключаемые атрибуты</h2>
                <p class="description">Выберите атрибуты товаров, которые НЕ будут включены в Schema.org разметку.</p>
                
                <?php
                $wc_attributes = wc_get_attribute_taxonomies();
                $excluded = $options['excluded_attributes'] ?? array();
                
                if ( ! empty( $wc_attributes ) ) :
                ?>
                <div style="margin: 15px 0;">
                    <input type="text" id="attr-search" placeholder="Поиск атрибутов..." 
                           style="width: 300px; margin-right: 10px;">
                    <button type="button" id="select-all-attrs" class="button button-small">Выбрать все</button>
                    <button type="button" id="deselect-all-attrs" class="button button-small">Снять все</button>
                    <span id="attr-counter" style="margin-left: 15px; color: #666;">
                        Выбрано: <strong><?php echo count( $excluded ); ?></strong> из <?php echo count( $wc_attributes ); ?>
                    </span>
                </div>
                
                <div id="attributes-list" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9; border-radius: 4px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 5px;">
                        <?php foreach ( $wc_attributes as $attribute ) :
                            $attr_name = wc_attribute_taxonomy_name( $attribute->attribute_name );
                            $checked = in_array( $attr_name, $excluded, true );
                        ?>
                        <label class="attr-item" style="display: flex; align-items: center; padding: 5px 8px; background: #fff; border-radius: 3px; cursor: pointer;" 
                               data-name="<?php echo esc_attr( strtolower( $attribute->attribute_label . ' ' . $attribute->attribute_name . ' ' . $attr_name ) ); ?>">
                            <input type="checkbox" name="yandex_schema_options[excluded_attributes][]" 
                                   value="<?php echo esc_attr( $attr_name ); ?>" <?php checked( $checked ); ?>
                                   style="margin-right: 8px;">
                            <span style="flex: 1;">
                                <?php echo esc_html( $attribute->attribute_label ); ?>
                                <code style="font-size: 10px; color: #999; margin-left: 5px;"><?php echo esc_html( $attr_name ); ?></code>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <p class="description" style="margin-top: 10px;">Отмеченные атрибуты будут исключены из additionalProperty и специальных свойств (color, material и т.д.)</p>
                
                <script>
                jQuery(document).ready(function($) {
                    // Search filter
                    $('#attr-search').on('input', function() {
                        var search = $(this).val().toLowerCase();
                        $('.attr-item').each(function() {
                            var name = String($(this).attr('data-name') || '').toLowerCase();
                            $(this).toggle(name.indexOf(search) !== -1);
                        });
                    });
                    
                    // Select all
                    $('#select-all-attrs').on('click', function() {
                        $('.attr-item:visible input[type="checkbox"]').prop('checked', true);
                        updateCounter();
                    });
                    
                    // Deselect all
                    $('#deselect-all-attrs').on('click', function() {
                        $('.attr-item:visible input[type="checkbox"]').prop('checked', false);
                        updateCounter();
                    });
                    
                    // Update counter
                    function updateCounter() {
                        var checked = $('.attr-item input[type="checkbox"]:checked').length;
                        var total = $('.attr-item').length;
                        $('#attr-counter strong').text(checked);
                    }
                    
                    $('.attr-item input[type="checkbox"]').on('change', updateCounter);
                });
                </script>
                <?php else : ?>
                <p style="color: #666; font-style: italic;">Атрибуты WooCommerce не найдены.</p>
                <?php endif; ?>

                <h2>Филиалы компании</h2>
                <p class="description">Добавьте филиалы вашей компании. Каждый филиал будет отображаться как отдельный LocalBusiness с привязкой к основной организации (branchOf).</p>
                
                <div id="yandex-schema-branches">
                    <?php 
                    $branches = $this->branches;
                    if ( empty( $branches ) ) : 
                    ?>
                    <p class="no-branches-message" style="color: #666; font-style: italic;">Филиалы не добавлены. Нажмите "Добавить филиал" для добавления.</p>
                    <?php 
                    endif;
                    foreach ( $branches as $index => $branch ) : 
                    ?>
                    <div class="branch-item" style="background: #f9f9f9; padding: 15px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <h3 style="margin: 0;">Филиал #<?php echo $index + 1; ?></h3>
                            <button type="button" class="button remove-branch" style="color: #a00;">Удалить филиал</button>
                        </div>
                        
                        <table class="form-table" style="margin: 0;">
                            <tr>
                                <th><label>Название филиала *</label></th>
                                <td>
                                    <input type="text" name="yandex_schema_branches[<?php echo $index; ?>][name]" 
                                           value="<?php echo esc_attr( $branch['name'] ?? '' ); ?>" class="regular-text"
                                           placeholder="Например: УралГипс - Центральный офис">
                                </td>
                            </tr>
                            <tr>
                                <th><label>Телефон</label></th>
                                <td>
                                    <input type="text" name="yandex_schema_branches[<?php echo $index; ?>][phone]" 
                                           value="<?php echo esc_attr( $branch['phone'] ?? '' ); ?>" class="regular-text"
                                           placeholder="+7 (XXX) XXX-XX-XX">
                                </td>
                            </tr>
                            <tr>
                                <th><label>Email</label></th>
                                <td>
                                    <input type="email" name="yandex_schema_branches[<?php echo $index; ?>][email]" 
                                           value="<?php echo esc_attr( $branch['email'] ?? '' ); ?>" class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th><label>Регион</label></th>
                                <td>
                                    <input type="text" name="yandex_schema_branches[<?php echo $index; ?>][region]" 
                                           value="<?php echo esc_attr( $branch['region'] ?? '' ); ?>" class="regular-text"
                                           placeholder="Удмуртская Республика">
                                </td>
                            </tr>
                            <tr>
                                <th><label>Город</label></th>
                                <td>
                                    <input type="text" name="yandex_schema_branches[<?php echo $index; ?>][city]" 
                                           value="<?php echo esc_attr( $branch['city'] ?? '' ); ?>" class="regular-text"
                                           placeholder="Ижевск">
                                </td>
                            </tr>
                            <tr>
                                <th><label>Адрес (улица)</label></th>
                                <td>
                                    <input type="text" name="yandex_schema_branches[<?php echo $index; ?>][street]" 
                                           value="<?php echo esc_attr( $branch['street'] ?? '' ); ?>" class="regular-text"
                                           placeholder="ул. Пушкинская, 268">
                                </td>
                            </tr>
                            <tr>
                                <th><label>Индекс</label></th>
                                <td>
                                    <input type="text" name="yandex_schema_branches[<?php echo $index; ?>][postal]" 
                                           value="<?php echo esc_attr( $branch['postal'] ?? '' ); ?>" class="small-text"
                                           placeholder="426000">
                                </td>
                            </tr>
                            <tr>
                                <th><label>Координаты</label></th>
                                <td>
                                    <input type="text" name="yandex_schema_branches[<?php echo $index; ?>][lat]" 
                                           value="<?php echo esc_attr( $branch['lat'] ?? '' ); ?>" class="small-text" placeholder="Широта">
                                    <input type="text" name="yandex_schema_branches[<?php echo $index; ?>][lng]" 
                                           value="<?php echo esc_attr( $branch['lng'] ?? '' ); ?>" class="small-text" placeholder="Долгота">
                                </td>
                            </tr>
                            <tr>
                                <th><label>Часы работы (Пн-Пт)</label></th>
                                <td>
                                    <input type="time" name="yandex_schema_branches[<?php echo $index; ?>][hours_weekday_open]" 
                                           value="<?php echo esc_attr( $branch['hours_weekday_open'] ?? '08:00' ); ?>">
                                    —
                                    <input type="time" name="yandex_schema_branches[<?php echo $index; ?>][hours_weekday_close]" 
                                           value="<?php echo esc_attr( $branch['hours_weekday_close'] ?? '18:00' ); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th><label>Часы работы (Сб)</label></th>
                                <td>
                                    <input type="time" name="yandex_schema_branches[<?php echo $index; ?>][hours_saturday_open]" 
                                           value="<?php echo esc_attr( $branch['hours_saturday_open'] ?? '09:00' ); ?>">
                                    —
                                    <input type="time" name="yandex_schema_branches[<?php echo $index; ?>][hours_saturday_close]" 
                                           value="<?php echo esc_attr( $branch['hours_saturday_close'] ?? '15:00' ); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th><label>Воскресенье</label></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="yandex_schema_branches[<?php echo $index; ?>][hours_sunday_closed]" 
                                               value="1" <?php checked( $branch['hours_sunday_closed'] ?? true, true ); ?>>
                                        Выходной
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <button type="button" id="add-branch" class="button button-secondary">+ Добавить филиал</button>

                <?php submit_button( 'Сохранить настройки' ); ?>
            </form>
            
            <script>
            jQuery(document).ready(function($) {
                var branchIndex = <?php echo count( $branches ); ?>;
                
                // Add new branch
                $('#add-branch').on('click', function() {
                    // Hide "no branches" message
                    $('.no-branches-message').remove();
                    
                    var template = `
                    <div class="branch-item" style="background: #f9f9f9; padding: 15px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <h3 style="margin: 0;">Филиал #${branchIndex + 1}</h3>
                            <button type="button" class="button remove-branch" style="color: #a00;">Удалить филиал</button>
                        </div>
                        
                        <table class="form-table" style="margin: 0;">
                            <tr>
                                <th><label>Название филиала *</label></th>
                                <td>
                                    <input type="text" name="yandex_schema_branches[${branchIndex}][name]" 
                                           value="" class="regular-text"
                                           placeholder="Например: УралГипс - Центральный офис">
                                </td>
                            </tr>
                            <tr>
                                <th><label>Телефон</label></th>
                                <td>
                                    <input type="text" name="yandex_schema_branches[${branchIndex}][phone]" 
                                           value="" class="regular-text"
                                           placeholder="+7 (XXX) XXX-XX-XX">
                                </td>
                            </tr>
                            <tr>
                                <th><label>Email</label></th>
                                <td>
                                    <input type="email" name="yandex_schema_branches[${branchIndex}][email]" 
                                           value="" class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th><label>Регион</label></th>
                                <td>
                                    <input type="text" name="yandex_schema_branches[${branchIndex}][region]" 
                                           value="" class="regular-text"
                                           placeholder="Удмуртская Республика">
                                </td>
                            </tr>
                            <tr>
                                <th><label>Город</label></th>
                                <td>
                                    <input type="text" name="yandex_schema_branches[${branchIndex}][city]" 
                                           value="" class="regular-text"
                                           placeholder="Ижевск">
                                </td>
                            </tr>
                            <tr>
                                <th><label>Адрес (улица)</label></th>
                                <td>
                                    <input type="text" name="yandex_schema_branches[${branchIndex}][street]" 
                                           value="" class="regular-text"
                                           placeholder="ул. Пушкинская, 268">
                                </td>
                            </tr>
                            <tr>
                                <th><label>Индекс</label></th>
                                <td>
                                    <input type="text" name="yandex_schema_branches[${branchIndex}][postal]" 
                                           value="" class="small-text"
                                           placeholder="426000">
                                </td>
                            </tr>
                            <tr>
                                <th><label>Координаты</label></th>
                                <td>
                                    <input type="text" name="yandex_schema_branches[${branchIndex}][lat]" 
                                           value="" class="small-text" placeholder="Широта">
                                    <input type="text" name="yandex_schema_branches[${branchIndex}][lng]" 
                                           value="" class="small-text" placeholder="Долгота">
                                </td>
                            </tr>
                            <tr>
                                <th><label>Часы работы (Пн-Пт)</label></th>
                                <td>
                                    <input type="time" name="yandex_schema_branches[${branchIndex}][hours_weekday_open]" 
                                           value="08:00">
                                    —
                                    <input type="time" name="yandex_schema_branches[${branchIndex}][hours_weekday_close]" 
                                           value="18:00">
                                </td>
                            </tr>
                            <tr>
                                <th><label>Часы работы (Сб)</label></th>
                                <td>
                                    <input type="time" name="yandex_schema_branches[${branchIndex}][hours_saturday_open]" 
                                           value="09:00">
                                    —
                                    <input type="time" name="yandex_schema_branches[${branchIndex}][hours_saturday_close]" 
                                           value="15:00">
                                </td>
                            </tr>
                            <tr>
                                <th><label>Воскресенье</label></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="yandex_schema_branches[${branchIndex}][hours_sunday_closed]" 
                                               value="1" checked>
                                        Выходной
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>`;
                    
                    $('#yandex-schema-branches').append(template);
                    branchIndex++;
                    updateBranchNumbers();
                });
                
                // Remove branch
                $(document).on('click', '.remove-branch', function() {
                    $(this).closest('.branch-item').remove();
                    updateBranchNumbers();
                });
                
                // Update branch numbers
                function updateBranchNumbers() {
                    $('.branch-item').each(function(index) {
                        $(this).find('h3').text('Филиал #' + (index + 1));
                    });
                }
            });
            </script>

            <hr>
            <h2>Мета-поля товаров</h2>
            <p>В редакторе каждого товара WooCommerce добавлены поля:</p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li><strong>GTIN</strong> — штрих-код товара (EAN, UPC)</li>
                <li><strong>MPN</strong> — артикул производителя</li>
                <li><strong>Бренд</strong> — название бренда</li>
            </ul>
        </div>
        <?php
    }
}

// Initialize plugin
add_action( 'plugins_loaded', function() {
    Yandex_Schema_WooCommerce::get_instance();
} );

// Activation hook
register_activation_hook( __FILE__, function() {
    // Flush rewrite rules
    flush_rewrite_rules();
} );

// Deactivation hook
register_deactivation_hook( __FILE__, function() {
    // Flush rewrite rules
    flush_rewrite_rules();
} );
