<?php

/*
|--------------------------------------------------------------------------
| Geographic Mapping — Continent / Country
|--------------------------------------------------------------------------
|
| Static mapping of ISO 3166-1 alpha-2 country codes to continent slugs.
| Used by MapApiController for drill-down aggregation.
|
| "middle-east" is a geopolitical grouping for conflict monitoring purposes.
| Countries listed under middle-east are removed from asia/africa.
|
*/

return [

    'continents' => [
        'africa' => ['name' => 'Africa', 'label_coords' => [20, 5]],
        'asia' => ['name' => 'Asia', 'label_coords' => [90, 40]],
        'europe' => ['name' => 'Europe', 'label_coords' => [15, 50]],
        'middle-east' => ['name' => 'Middle East', 'label_coords' => [45, 30]],
        'north-america' => ['name' => 'North America', 'label_coords' => [-100, 45]],
        'south-america' => ['name' => 'South America', 'label_coords' => [-60, -15]],
        'oceania' => ['name' => 'Oceania', 'label_coords' => [135, -25]],
    ],

    /*
    |--------------------------------------------------------------------------
    | Country → Continent Mapping
    |--------------------------------------------------------------------------
    | Only conflict-relevant and major countries listed. Extend as needed.
    */

    'country_to_continent' => [
        // ── MIDDLE EAST ──
        'AE' => 'middle-east', // UAE
        'BH' => 'middle-east', // Bahrain
        'EG' => 'middle-east', // Egypt (geopolitically ME for conflict monitoring)
        'IL' => 'middle-east', // Israel
        'IQ' => 'middle-east', // Iraq
        'IR' => 'middle-east', // Iran
        'JO' => 'middle-east', // Jordan
        'KW' => 'middle-east', // Kuwait
        'LB' => 'middle-east', // Lebanon
        'OM' => 'middle-east', // Oman
        'PS' => 'middle-east', // Palestine
        'QA' => 'middle-east', // Qatar
        'SA' => 'middle-east', // Saudi Arabia
        'SY' => 'middle-east', // Syria
        'TR' => 'middle-east', // Turkey
        'YE' => 'middle-east', // Yemen

        // ── EUROPE ──
        'AL' => 'europe', 'AT' => 'europe', 'BA' => 'europe', 'BE' => 'europe',
        'BG' => 'europe', 'BY' => 'europe', 'CH' => 'europe', 'CY' => 'europe',
        'CZ' => 'europe', 'DE' => 'europe', 'DK' => 'europe', 'EE' => 'europe',
        'ES' => 'europe', 'FI' => 'europe', 'FR' => 'europe', 'GB' => 'europe',
        'GE' => 'europe', 'GR' => 'europe', 'HR' => 'europe', 'HU' => 'europe',
        'IE' => 'europe', 'IS' => 'europe', 'IT' => 'europe', 'LT' => 'europe',
        'LV' => 'europe', 'MD' => 'europe', 'ME' => 'europe', 'MK' => 'europe',
        'NL' => 'europe', 'NO' => 'europe', 'PL' => 'europe', 'PT' => 'europe',
        'RO' => 'europe', 'RS' => 'europe', 'RU' => 'europe', 'SE' => 'europe',
        'SI' => 'europe', 'SK' => 'europe', 'UA' => 'europe', 'XK' => 'europe',

        // ── AFRICA ──
        'AO' => 'africa', 'BF' => 'africa', 'BI' => 'africa', 'BJ' => 'africa',
        'BW' => 'africa', 'CD' => 'africa', 'CF' => 'africa', 'CG' => 'africa',
        'CI' => 'africa', 'CM' => 'africa', 'DJ' => 'africa', 'DZ' => 'africa',
        'ER' => 'africa', 'ET' => 'africa', 'GA' => 'africa', 'GH' => 'africa',
        'GN' => 'africa', 'GW' => 'africa', 'KE' => 'africa', 'LR' => 'africa',
        'LS' => 'africa', 'LY' => 'africa', 'MA' => 'africa', 'MG' => 'africa',
        'ML' => 'africa', 'MR' => 'africa', 'MW' => 'africa', 'MZ' => 'africa',
        'NA' => 'africa', 'NE' => 'africa', 'NG' => 'africa', 'RW' => 'africa',
        'SD' => 'africa', 'SL' => 'africa', 'SN' => 'africa', 'SO' => 'africa',
        'SS' => 'africa', 'TD' => 'africa', 'TG' => 'africa', 'TN' => 'africa',
        'TZ' => 'africa', 'UG' => 'africa', 'ZA' => 'africa', 'ZM' => 'africa',
        'ZW' => 'africa',

        // ── ASIA ──
        'AF' => 'asia', 'AM' => 'asia', 'AZ' => 'asia', 'BD' => 'asia',
        'BT' => 'asia', 'CN' => 'asia', 'ID' => 'asia', 'IN' => 'asia',
        'JP' => 'asia', 'KG' => 'asia', 'KH' => 'asia', 'KP' => 'asia',
        'KR' => 'asia', 'KZ' => 'asia', 'LA' => 'asia', 'LK' => 'asia',
        'MM' => 'asia', 'MN' => 'asia', 'MY' => 'asia', 'NP' => 'asia',
        'PH' => 'asia', 'PK' => 'asia', 'SG' => 'asia', 'TH' => 'asia',
        'TJ' => 'asia', 'TL' => 'asia', 'TM' => 'asia', 'TW' => 'asia',
        'UZ' => 'asia', 'VN' => 'asia',

        // ── NORTH AMERICA ──
        'CA' => 'north-america', 'CU' => 'north-america', 'DO' => 'north-america',
        'GT' => 'north-america', 'HN' => 'north-america', 'HT' => 'north-america',
        'JM' => 'north-america', 'MX' => 'north-america', 'NI' => 'north-america',
        'PA' => 'north-america', 'SV' => 'north-america', 'US' => 'north-america',

        // ── SOUTH AMERICA ──
        'AR' => 'south-america', 'BO' => 'south-america', 'BR' => 'south-america',
        'CL' => 'south-america', 'CO' => 'south-america', 'EC' => 'south-america',
        'GY' => 'south-america', 'PE' => 'south-america', 'PY' => 'south-america',
        'SR' => 'south-america', 'UY' => 'south-america', 'VE' => 'south-america',

        // ── OCEANIA ──
        'AU' => 'oceania', 'FJ' => 'oceania', 'NZ' => 'oceania',
        'PG' => 'oceania', 'SB' => 'oceania',
    ],

    /*
    |--------------------------------------------------------------------------
    | Country Names (ISO alpha-2 → English name)
    |--------------------------------------------------------------------------
    */

    'country_names' => [
        // Middle East
        'AE' => 'United Arab Emirates', 'BH' => 'Bahrain', 'EG' => 'Egypt',
        'IL' => 'Israel', 'IQ' => 'Iraq', 'IR' => 'Iran', 'JO' => 'Jordan',
        'KW' => 'Kuwait', 'LB' => 'Lebanon', 'OM' => 'Oman', 'PS' => 'Palestine',
        'QA' => 'Qatar', 'SA' => 'Saudi Arabia', 'SY' => 'Syria', 'TR' => 'Turkey',
        'YE' => 'Yemen',
        // Europe
        'AL' => 'Albania', 'AT' => 'Austria', 'BA' => 'Bosnia & Herzegovina',
        'BE' => 'Belgium', 'BG' => 'Bulgaria', 'BY' => 'Belarus', 'CH' => 'Switzerland',
        'CY' => 'Cyprus', 'CZ' => 'Czech Republic', 'DE' => 'Germany', 'DK' => 'Denmark',
        'EE' => 'Estonia', 'ES' => 'Spain', 'FI' => 'Finland', 'FR' => 'France',
        'GB' => 'United Kingdom', 'GE' => 'Georgia', 'GR' => 'Greece', 'HR' => 'Croatia',
        'HU' => 'Hungary', 'IE' => 'Ireland', 'IS' => 'Iceland', 'IT' => 'Italy',
        'LT' => 'Lithuania', 'LV' => 'Latvia', 'MD' => 'Moldova', 'ME' => 'Montenegro',
        'MK' => 'North Macedonia', 'NL' => 'Netherlands', 'NO' => 'Norway', 'PL' => 'Poland',
        'PT' => 'Portugal', 'RO' => 'Romania', 'RS' => 'Serbia', 'RU' => 'Russia',
        'SE' => 'Sweden', 'SI' => 'Slovenia', 'SK' => 'Slovakia', 'UA' => 'Ukraine',
        'XK' => 'Kosovo',
        // Africa
        'AO' => 'Angola', 'BF' => 'Burkina Faso', 'BI' => 'Burundi', 'BJ' => 'Benin',
        'BW' => 'Botswana', 'CD' => 'DR Congo', 'CF' => 'Central African Republic',
        'CG' => 'Congo', 'CI' => 'Ivory Coast', 'CM' => 'Cameroon', 'DJ' => 'Djibouti',
        'DZ' => 'Algeria', 'ER' => 'Eritrea', 'ET' => 'Ethiopia', 'GA' => 'Gabon',
        'GH' => 'Ghana', 'GN' => 'Guinea', 'GW' => 'Guinea-Bissau', 'KE' => 'Kenya',
        'LR' => 'Liberia', 'LS' => 'Lesotho', 'LY' => 'Libya', 'MA' => 'Morocco',
        'MG' => 'Madagascar', 'ML' => 'Mali', 'MR' => 'Mauritania', 'MW' => 'Malawi',
        'MZ' => 'Mozambique', 'NA' => 'Namibia', 'NE' => 'Niger', 'NG' => 'Nigeria',
        'RW' => 'Rwanda', 'SD' => 'Sudan', 'SL' => 'Sierra Leone', 'SN' => 'Senegal',
        'SO' => 'Somalia', 'SS' => 'South Sudan', 'TD' => 'Chad', 'TG' => 'Togo',
        'TN' => 'Tunisia', 'TZ' => 'Tanzania', 'UG' => 'Uganda', 'ZA' => 'South Africa',
        'ZM' => 'Zambia', 'ZW' => 'Zimbabwe',
        // Asia
        'AF' => 'Afghanistan', 'AM' => 'Armenia', 'AZ' => 'Azerbaijan', 'BD' => 'Bangladesh',
        'BT' => 'Bhutan', 'CN' => 'China', 'ID' => 'Indonesia', 'IN' => 'India',
        'JP' => 'Japan', 'KG' => 'Kyrgyzstan', 'KH' => 'Cambodia', 'KP' => 'North Korea',
        'KR' => 'South Korea', 'KZ' => 'Kazakhstan', 'LA' => 'Laos', 'LK' => 'Sri Lanka',
        'MM' => 'Myanmar', 'MN' => 'Mongolia', 'MY' => 'Malaysia', 'NP' => 'Nepal',
        'PH' => 'Philippines', 'PK' => 'Pakistan', 'SG' => 'Singapore', 'TH' => 'Thailand',
        'TJ' => 'Tajikistan', 'TL' => 'Timor-Leste', 'TM' => 'Turkmenistan', 'TW' => 'Taiwan',
        'UZ' => 'Uzbekistan', 'VN' => 'Vietnam',
        // Americas
        'AR' => 'Argentina', 'BO' => 'Bolivia', 'BR' => 'Brazil', 'CA' => 'Canada',
        'CL' => 'Chile', 'CO' => 'Colombia', 'CU' => 'Cuba', 'DO' => 'Dominican Republic',
        'EC' => 'Ecuador', 'GT' => 'Guatemala', 'GY' => 'Guyana', 'HN' => 'Honduras',
        'HT' => 'Haiti', 'JM' => 'Jamaica', 'MX' => 'Mexico', 'NI' => 'Nicaragua',
        'PA' => 'Panama', 'PE' => 'Peru', 'PY' => 'Paraguay', 'SR' => 'Suriname',
        'SV' => 'El Salvador', 'US' => 'United States', 'UY' => 'Uruguay', 'VE' => 'Venezuela',
        // Oceania
        'AU' => 'Australia', 'FJ' => 'Fiji', 'NZ' => 'New Zealand',
        'PG' => 'Papua New Guinea', 'SB' => 'Solomon Islands',
    ],

];
