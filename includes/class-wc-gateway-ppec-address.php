<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class PayPal_Address {
	protected $_name;
	protected $_street1;
	protected $_street2;
	protected $_city;
	protected $_state;
	protected $_zip;
	protected $_country;
	protected $_phoneNumber;
	protected $_addressOwner;
	protected $_addressStatus;

	const AddressStatusNone                      = 'none';
	const AddressStatusConfirmed                 = 'Confirmed';
	const AddressStatusUnconfirmed               = 'Unconfirmed';

	public function setName( $name ) {
		$this->_name = $name;
	}

	public function getName() {
		return $this->_name;
	}

	public function setStreet1( $street1 ) {
		$this->_street1 = $street1;
	}

	public function getStreet1() {
		return $this->_street1;
	}

	public function setStreet2( $street2 ) {
		$this->_street2 = $street2;
	}

	public function getStreet2() {
		return $this->_street2;
	}

	public function setCity( $city ) {
		$this->_city = $city;
	}

	public function getCity() {
		return $this->_city;
	}

	public function setState( $state ) {
		$this->_state = $state;
	}

	public function getState() {
		return $this->_state;
	}

	public function setZip( $zip ) {
		$this->_zip = $zip;
	}

	public function getZip() {
		return $this->_zip;
	}

	public function setCountry( $country ) {
		$this->_country = $country;
	}

	public function getCountry() {
		return $this->_country;
	}

	public function setPhoneNumber( $phoneNumber ) {
		$this->_phoneNumber = $phoneNumber;
	}

	public function getPhoneNumber() {
		return $this->_phoneNumber;
	}

	public function setAddressOwner( $addressOwner ) {
		$this->_addressOwner = $addressOwner;
	}

	public function getAddressOwner() {
		return $this->_addressOwner;
	}

	public function setAddressStatus( $addressStatus ) {
		$this->_addressStatus = $addressStatus;
	}

	public function getAddressStatus() {
		return $this->_addressStatus;
	}

	public function normalizeAddress() {
		$this->normalizeCountry();
		$this->normalizeState();
		$this->normalizeZip();
	}

	public function normalizeCountry() {
		// Since many shopping carts might use the full country name for their internal representation of
		// the country, and since PayPal expects the ISO 3166-1 alpha-2 identifier, we'll attempt to
		// translate from the various internal representations that might be used.  Child classes can
		// override this method to provide language-specific or cart-specific translations.  Many Bothans
		// died to bring us this information...

		// This list was taken from https://developer.paypal.com/docs/classic/api/country_codes/.

		$translation_table = array(
			'albania'                          => 'AL',
			'algeria'                          => 'DZ',
			'andorra'                          => 'AD',
			'angola'                           => 'AO',
			'anguilla'                         => 'AI',
			'antigua and barbuda'              => 'AG',
			'argentina'                        => 'AR',
			'armenia'                          => 'AM',
			'aruba'                            => 'AW',
			'australia'                        => 'AU',
			'austria'                          => 'AT',
			'azerbaijan'                       => 'AZ',
			'bahamas'                          => 'BS',
			'bahrain'                          => 'BH',
			'barbados'                         => 'BB',
			'belgium'                          => 'BE',
			'belize'                           => 'BZ',
			'benin'                            => 'BJ',
			'bermuda'                          => 'BM',
			'bhutan'                           => 'BT',
			'bolivia'                          => 'BO',
			'bosnia-herzegovina'               => 'BA',
			'botswana'                         => 'BW',
			'brazil'                           => 'BR',
			'brunei darussalam'                => 'BN',
			'bulgaria'                         => 'BG',
			'burkina faso'                     => 'BF',
			'burundi'                          => 'BI',
			'cambodia'                         => 'KH',
			'canada'                           => 'CA',
			'cape verde'                       => 'CV',
			'cayman islands'                   => 'KY',
			'chad'                             => 'TD',
			'chile'                            => 'CL',
			'china'                            => 'CN',
			'colombia'                         => 'CO',
			'comoros'                          => 'KM',
			'democratic republic of congo'     => 'CD',
			'congo'                            => 'CG',
			'cook islands'                     => 'CK',
			'costa rica'                       => 'CR',
			'croatia'                          => 'HR',
			'cyprus'                           => 'CY',
			'czech republic'                   => 'CZ',
			'denmark'                          => 'DK',
			'djibouti'                         => 'DJ',
			'dominica'                         => 'DM',
			'dominican republic'               => 'DO',
			'ecuador'                          => 'EC',
			'egypt'                            => 'EG',
			'el salvador'                      => 'SV',
			'eriteria'                         => 'ER',
			'estonia'                          => 'EE',
			'ethiopia'                         => 'ET',
			'falkland islands (malvinas)'      => 'FK',
			// This derivation doesn't show up in the list, but seems obvious
			'falkland islands'                 => 'FK',
			'fiji'                             => 'FJ',
			'finland'                          => 'FI',
			'france'                           => 'FR',
			'french guiana'                    => 'GF',
			'french polynesia'                 => 'PF',
			'gabon'                            => 'GA',
			'gambia'                           => 'GM',
			'georgia'                          => 'GE',
			'germany'                          => 'DE',
			'gibraltar'                        => 'GI',
			'greece'                           => 'GR',
			'greenland'                        => 'GL',
			'grenada'                          => 'GD',
			'guadeloupe'                       => 'GP',
			'guam'                             => 'GU',
			'guatemala'                        => 'GT',
			'guinea'                           => 'GN',
			'guinea bissau'                    => 'GW',
			'guyana'                           => 'GY',
			'holy see (vatican city state)'    => 'VA',
			// This derivation doesn't show up in the list, but seems obvious
			'holy see'                         => 'VA',
			'honduras'                         => 'HN',
			'hong kong'                        => 'HK',
			'hungary'                          => 'HU',
			'iceland'                          => 'IS',
			'india'                            => 'IN',
			'indonesia'                        => 'ID',
			'ireland'                          => 'IE',
			'israel'                           => 'IL',
			'italy'                            => 'IT',
			'jamaica'                          => 'JM',
			'japan'                            => 'JP',
			'jordan'                           => 'JO',
			'kazakhstan'                       => 'KZ',
			'kenya'                            => 'KE',
			'kiribati'                         => 'KI',
			'korea, republic of'               => 'KR',
			// This derivation doesn't show up in the list, but seems obvious
			'republic of korea'                => 'KR',
			'kuwait'                           => 'KW',
			'kyrgyzstan'                       => 'KG',
			'laos'                             => 'LA',
			'latvia'                           => 'LV',
			'lesotho'                          => 'LS',
			'liechtenstein'                    => 'LI',
			'lithuania'                        => 'LT',
			'luxembourg'                       => 'LU',
			'madagascar'                       => 'MG',
			'malawi'                           => 'MW',
			'malaysia'                         => 'MY',
			'maldives'                         => 'MV',
			'mali'                             => 'ML',
			'malta'                            => 'MT',
			'marshall islands'                 => 'MH',
			'martinique'                       => 'MQ',
			'mauritania'                       => 'MR',
			'mauritius'                        => 'MU',
			'mayotte'                          => 'YT',
			'mexico'                           => 'MX',
			'micronesia, federated states of'  => 'FM',
			// The next two derivations don't show up in the list, but seem obvious
			'federated states of micronesia'   => 'FM',
			'micronesia'                       => 'FM',
			'mongolia'                         => 'MN',
			'montserrat'                       => 'MS',
			'morocco'                          => 'MA',
			'mozambique'                       => 'MZ',
			'namibia'                          => 'NA',
			'nauru'                            => 'NR',
			'nepal'                            => 'NP',
			'netherlands'                      => 'NL',
			'netherlands antilles'             => 'AN',
			'new caledonia'                    => 'NC',
			'new zealand'                      => 'NZ',
			'nicaragua'                        => 'NI',
			'niger'                            => 'NE',
			'niue'                             => 'NU',
			'norfolk island'                   => 'NF',
			'norway'                           => 'NO',
			'oman'                             => 'OM',
			'palau'                            => 'PW',
			'panama'                           => 'PA',
			'papau new guinea'                 => 'PG',
			'peru'                             => 'PE',
			'philippines'                      => 'PH',
			'pitcairn'                         => 'PN',
			'poland'                           => 'PL',
			'portugal'                         => 'PT',
			'qatar'                            => 'QA',
			'reunion'                          => 'RE',
			'romania'                          => 'RO',
			'russian federation'               => 'RU',
			// This derivation doesn't show up in the list, but seems obvious
			'russia'                           => 'RU',
			'rwanda'                           => 'RW',
			'saint helena'                     => 'SH',
			'saint kitts and nevis'            => 'KN',
			'saint lucia'                      => 'LC',
			'saint pierre and miquelon'        => 'PM',
			'saint vincent and the grenadines' => 'VC',
			'samoa'                            => 'WS',
			'san marino'                       => 'SM',
			'sao tome and principe'            => 'ST',
			'saudi arabia'                     => 'SA',
			'senegal'                          => 'SN',
			'serbia'                           => 'RS',
			'seychelles'                       => 'SC',
			'sierra leone'                     => 'SL',
			'singapore'                        => 'SG',
			'slovakia'                         => 'SK',
			'slovenia'                         => 'SI',
			'solomon islands'                  => 'SB',
			'somalia'                          => 'SO',
			'south africa'                     => 'ZA',
			'south korea'                      => 'KR',
			'spain'                            => 'ES',
			'sri lanka'                        => 'LK',
			'suriname'                         => 'SR',
			'svalbard and jan mayen'           => 'SJ',
			'swaziland'                        => 'SZ',
			'sweden'                           => 'SE',
			'switzerland'                      => 'CH',
			'taiwan, province of china'        => 'TW',
			// This derivation doesn't show up in the list, but seems obvious
			'taiwan'                           => 'TW',
			'tajikistan'                       => 'TJ',
			'tanzania, united republic of'     => 'TZ',
			// The next two derivations don't show up in the list, but seem obvious
			'united republic of tanzania'      => 'TZ',
			'tanzania'                         => 'TZ',
			'thailand'                         => 'TH',
			'togo'                             => 'TG',
			'tonga'                            => 'TO',
			'trinidad and tobago'              => 'TT',
			'tunisia'                          => 'TN',
			'turkey'                           => 'TR',
			'turkmenistan'                     => 'TM',
			'turks and caicos islands'         => 'TC',
			// This derivation doesn't show up in the list, but seems obvious
			'turks and caicos'                 => 'TC',
			'tuvalu'                           => 'TV',
			'uganda'                           => 'UG',
			'ukraine'                          => 'UA',
			'united arab emirates'             => 'AE',
			'united kingdom'                   => 'GB',
			'united states'                    => 'US',
			// This derivation doesn't show up in the list, but seems obvious
			'united states of america'         => 'US',
			'uruguay'                          => 'UY',
			'vanuatu'                          => 'VU',
			'venezuela'                        => 'VE',
			'vietnam'                          => 'VN',
			'virgin islands, british'          => 'VG',
			// This derivation doesn't show up in the list, but seems obvious
			'british virgin islands'           => 'VG',
			'wallis and futana'                => 'WF',
			'yemen'                            => 'YE',
			'zambia'                           => 'ZM',
			// This one is here because some carts will make the mistake of using 'uk' instead of 'gb'.
			'uk'                               => 'GB'
		);

		// And now, the actual translation is as simple as...
		if ( array_key_exists( strtolower( trim( $this->_country ) ), $translation_table ) ) {
			$this->_country = $translation_table[ strtolower( trim( $this->_country ) ) ];
		}
	}

	public function normalizeState() {
		// Since some shopping carts might use the full state name for their internal representation of the
		// state, and since PayPal expects the 2-character state/province abbreviation (for US/Canada
		// addresses, at least), we'll attempt to translate from the various internal representations
		// that might be used.  Child classes can override this method to provide additional
		// language-specific or cart-specific translations.

		// This call should be made AFTER normalizeCountry() has been called, so that the country can be
		// properly detected.

		// PayPal's documentation also defines state codes for Italy and the Netherlands, so we'll provide
		// translations for those as well.

		$translation_table = array();

		if ( 'AR' == $this->_country ) {
			$translation_table = array(
				'ciudad autónoma de buenos aires' => 'C',
				'buenos aires'                    => 'B',
				'catamarca'                       => 'K',
				'chaco'                           => 'H',
				'chubut'                          => 'U',
				'corrientes'                      => 'W',
				'córdoba'                         => 'X',
				'entre ríos'                      => 'E',
				'formosa'                         => 'P',
				'jujuy'                           => 'Y',
				'la pampa'                        => 'L',
				'la rioja'                        => 'F',
				'mendoza'                         => 'M',
				'misiones'                        => 'N',
				'neuquén'                         => 'Q',
				'río negro'                       => 'R',
				'salta'                           => 'A',
				'san juan'                        => 'J',
				'san luis'                        => 'D',
				'santa cruz'                      => 'Z',
				'santa fe'                        => 'S',
				'santiago del estero'             => 'G',
				'tierra del fuego'                => 'V',
				'tucumán'                         => 'T',
			);
		} elseif ( 'CA' == $this->_country ) {
			$translation_table = array(
				'alberta'               => 'AB',
				'british columbia'      => 'BC',
				'manitoba'              => 'MB',
				'new brunswick'         => 'NB',
				'newfoundland'          => 'NL',
				'northwest territories' => 'NT',
				'nova scotia'           => 'NS',
				'nunavut'               => 'NU',
				'ontario'               => 'ON',
				'prince edward island'  => 'PE',
				'quebec'                => 'QC',
				'saskatchewan'          => 'SK',
				'yukon'                 => 'YT',
				// This derivation doesn't show up on the list, but seems obvious
				'yukon territory'       => 'YT'
			);
		} elseif ( 'CN' == $this->_country ) {
			$translation_table = array(
					'cn-yn' => 'CN1',
					'cn-bj' => 'CN2',
					'cn-tj' => 'CN3',
					'cn-he' => 'CN4',
					'cn-sx' => 'CN5',
					'cn-nm' => 'CN6',
					'cn-ln' => 'CN7',
					'cn-jl' => 'CN8',
					'cn-hl' => 'CN9',
					'cn-sh' => 'CN10',
					'cn-js' => 'CN11',
					'cn-zj' => 'CN12',
					'cn-ah' => 'CN13',
					'cn-fj' => 'CN14',
					'cn-jx' => 'CN15',
					'cn-sd' => 'CN16',
					'cn-ha' => 'CN17',
					'cn-hb' => 'CN18',
					'cn-hn' => 'CN19',
					'cn-gd' => 'CN20',
					'cn-gx' => 'CN21',
					'cn-hi' => 'CN22',
					'cn-cq' => 'CN23',
					'cn-sc' => 'CN24',
					'cn-gz' => 'CN25',
					'cn-sn' => 'CN26',
					'cn-gs' => 'CN27',
					'cn-qh' => 'CN28',
					'cn-nx' => 'CN29',
					'cn-mo' => 'CN30',
					'cn-xz' => 'CN31',
					'cn-xj' => 'CN32',
			);
		} elseif ( 'ES' == $this->_country ) {
			$translation_table = array(
				'a coruÑa'               =>'C',
				'araba/Álava'            =>'VI',
				'albacete'               =>'AB',
				'alicante'               =>'A' ,
				'almerÍa'                =>'AL',
				'asturias'               =>'O' ,
				'Ávila'                  =>'AV',
				'badajoz'                =>'BA',
				'baleares'               =>'PM',
				'barcelona'              =>'B' ,
				'burgos'                 =>'BU',
				'cÁceres'                =>'CC',
				'cÁdiz'                  =>'CA',
				'cantabria'              =>'S' ,
				'castellÓn'              =>'CS',
				'ceuta'                  =>'CE',
				'ciudad real'            =>'CR',
				'cÓrdoba'                =>'CO',
				'cuenca'                 =>'CU',
				'girona'                 =>'GI',
				'granada'                =>'GR',
				'guadalajara'            =>'GU',
				'gipuzkoa'               =>'SS',
				'huelva'                 =>'H' ,
				'huesca'                 =>'HU',
				'jaÉn'                   =>'J',
				'la rioja'               =>'LO',
				'las palmas'             =>'GC',
				'leÓn'                   =>'LE',
				'lleida'                 =>'L' ,
				'lugo'                   =>'LU',
				'madrid'                 =>'M' ,
				'mÁlaga'                 =>'MA',
				'melilla'                =>'ML',
				'murcia'                 =>'MU',
				'navarra'                =>'NA',
				'ourense'                =>'OR',
				'palencia'               =>'P' ,
				'pontevedra'             =>'PO',
				'salamanca'              =>'SA',
				'santa cruz de tenerife' =>'TF',
				'segovia'                =>'SG',
				'sevilla'                =>'SE',
				'soria'                  =>'SO',
				'tarragona'              =>'T' ,
				'teruel'                 =>'TE',
				'toledo'                 =>'TO',
				'valencia'               =>'V' ,
				'valladolid'             =>'VA',
				'bizkaia'                =>'BI',
				'zamora'                 =>'ZA',
				'zaragoza'               =>'Z' ,
			);
		} elseif ( 'IE' == $this->_country ) {
			$translation_table = array(
				'co clare'     => 'CE',
				'co cork'      => 'CK',
				'co cavan'     => 'CN',
				'co carlow'    => 'CW',
				'co donegal'   => 'DL',
				// All of these should be mapped to Dublin start
				'co dublin'    => 'DN',
				'dublin 1'     => 'DN',
				'dublin 2'     => 'DN',
				'dublin 3'     => 'DN',
				'dublin 4'     => 'DN',
				'dublin 5'     => 'DN',
				'dublin 6'     => 'DN',
				'dublin 6w'    => 'DN',
				'dublin 7'     => 'DN',
				'dublin 8'     => 'DN',
				'dublin 9'     => 'DN',
				'dublin 10'    => 'DN',
				'dublin 11'    => 'DN',
				'dublin 12'    => 'DN',
				'dublin 13'    => 'DN',
				'dublin 14'    => 'DN',
				'dublin 15'    => 'DN',
				'dublin 16'    => 'DN',
				'dublin 17'    => 'DN',
				'dublin 18'    => 'DN',
				'dublin 20'    => 'DN',
				'dublin 22'    => 'DN',
				'dublin 24'    => 'DN',
				// All of these should be mapped to Dublin end
				'co galway'    => 'GY',
				'co kildare'   => 'KE',
				'co kilkenny'  => 'KK',
				'co kerry'     => 'KY',
				'co longford'  => 'LD',
				'co louth'     => 'LH',
				'co limerick'  => 'LK',
				'co leitrim'   => 'LM',
				'co laois'     => 'LS',
				'co meath'     => 'MH',
				'co monaghan'  => 'MN',
				'co mayo'      => 'MO',
				'co offaly'    => 'OY',
				'co roscommon' => 'RN',
				'co sligo'     => 'SO',
				'co tipperary' => 'TY',
				'co waterford' => 'WD',
				'co westmeath' => 'WH',
				'co wicklow'   => 'WW',
				'co wexford'   => 'WX',
			);
		} elseif ( 'ID' == $this->_country ) {
			$translation_table = array(
				'id-ac' => 'AC',
				'id-ba' => 'BA',
				'id-bb' => 'BB',
				'id-be' => 'BE',
				'id-bt' => 'BT',
				'id-go' => 'GO',
				'id-ja' => 'JA',
				'id-jb' => 'JB',
				'id-ji' => 'JI',
				'id-jk' => 'JK',
				'id-jt' => 'JT',
				'id-kb' => 'KB',
				'id-ki' => 'KI',
				'id-kr' => 'KR',
				'id-ks' => 'KS',
				'id-kt' => 'KT',
				'id-ku' => 'KU',
				'id-la' => 'LA',
				'id-ma' => 'MA',
				'id-mu' => 'MU',
				'id-nb' => 'NB',
				'id-nt' => 'NT',
				'id-pa' => 'PA',
				'id-pb' => 'PB',
				'id-ri' => 'RI',
				'id-sa' => 'SA',
				'id-sb' => 'SB',
				'id-sg' => 'SG',
				'id-sn' => 'SN',
				'id-sr' => 'SR',
				'id-ss' => 'SS',
				'id-st' => 'ST',
				'id-su' => 'SU',
				'id-yo' => 'YO',
			);
		} elseif ( 'IN' == $this->_country ) {
			$translation_table = array(
				'andaman and nicobar islands' => 'AN',
				'andhra pradesh'              => 'AP',
				//'apo'                         => '',
				'arunachal pradesh'           => 'AR',
				'assam'                       => 'AS',
				'bihar'                       => 'BR',
				'chandigarh'                  => 'CH',
				'chhattisgarh'                => 'CT',
				'dadra and nagar haveli'      => 'DN',
				'daman and diu'               => 'DD',
				'delhi (nct)'                 => 'DL',
				'goa'                         => 'GA',
				'gujarat'                     => 'GJ',
				'haryana'                     => 'HR',
				'himachal pradesh'            => 'HP',
				'jammu and kashmir'           => 'JK',
				'jharkhand'                   => 'JH',
				'karnataka'                   => 'KA',
				'kerala'                      => 'KL',
				'lakshadweep'                 => 'LD',
				'madhya pradesh'              => 'MP',
				'maharashtra'                 => 'MH',
				'manipur'                     => 'MN',
				'meghalaya'                   => 'ML',
				'mizoram'                     => 'MZ',
				'nagaland'                    => 'NL',
				'odisha'                      => 'OR',
				'puducherry'                  => 'PY',
				'punjab'                      => 'PB',
				'rajasthan'                   => 'RJ',
				'sikkim'                      => 'SK',
				'tamil nadu'                  => 'TN',
				'telangana'                   => 'TS',
				'tripura'                     => 'TR',
				'uttar pradesh'               => 'UP',
				'uttarakhand'                 => 'UK',
				'west bengal'                 => 'WB',
			);
		} elseif ( 'IT' == $this->_country ) {
			$translation_table = array(
				'agrigento'             => 'AG',
				'alessandria'           => 'AL',
				'ancona'                => 'AN',
				'aosta'                 => 'AO',
				'arezzo'                => 'AR',
				'ascoli piceno'         => 'AP',
				'asti'                  => 'AT',
				'avellino'              => 'AV',
				'bari'                  => 'BA',
				'belluno'               => 'BL',
				'benevento'             => 'BN',
				'bergamo'               => 'BG',
				'biella'                => 'BI',
				'bologna'               => 'BO',
				'bolzano'               => 'BZ',
				'brescia'               => 'BS',
				'brindisi'              => 'BR',
				'cagliari'              => 'CA',
				'caltanissetta'         => 'CL',
				'campobasso'            => 'CB',
				'caserta'               => 'CE',
				'catania'               => 'CT',
				'catanzaro'             => 'CZ',
				'chieti'                => 'CH',
				'como'                  => 'CO',
				'cosenza'               => 'CS',
				'cremona'               => 'CR',
				'crotone'               => 'KR',
				'cuneo'                 => 'CN',
				'enna'                  => 'EN',
				'ferrara'               => 'FE',
				'firenze'               => 'FI',
				'foggia'                => 'FG',
				'forli-cesena'          => 'FO',
				'frosinone'             => 'FR',
				'genova'                => 'GE',
				'gorizia'               => 'GO',
				'grosseto'              => 'GR',
				'imperia'               => 'IM',
				'isernia'               => 'IS',
				'la spezia'             => 'SP',
				'l\'aquila'             => 'AQ',
				'latina'                => 'LT',
				'lecce'                 => 'LE',
				'lecco'                 => 'LC',
				'livorno'               => 'LI',
				'lodi'                  => 'LO',
				'lucca'                 => 'LU',
				'macerata'              => 'MC',
				'mantova'               => 'MN',
				'massa-carrara'         => 'MS',
				'matera'                => 'MT',
				'messina'               => 'ME',
				'milano'                => 'MI',
				'modena'                => 'MO',
				'monza e brianza'       => 'MB',
				// The next couple of derivations are based off information from Wikipedia
				'monza and brianza'     => 'MB',
				'monza e della brianza' => 'MB',
				'napoli'                => 'NA',
				'novara'                => 'NO',
				'nuoro'                 => 'NU',
				'oristano'              => 'OR',
				'padova'                => 'PD',
				'palermo'               => 'PA',
				'parma'                 => 'PR',
				'pavia'                 => 'PV',
				'perugia'               => 'PG',
				'pesaro'                => 'PS',
				'pescara'               => 'PE',
				'piacenza'              => 'PC',
				'pisa'                  => 'PI',
				'pistoia'               => 'PT',
				'pordenone'             => 'PN',
				'potenza'               => 'PZ',
				'prato'                 => 'PO',
				'ragusa'                => 'RG',
				'ravenna'               => 'RA',
				'reggio calabria'       => 'RC',
				'reggio emilia'         => 'RE',
				'rieti'                 => 'RI',
				'rimini'                => 'RN',
				'roma'                  => 'RM',
				'rovigo'                => 'RO',
				'salerno'               => 'SA',
				'sassari'               => 'SS',
				'savona'                => 'SV',
				'siena'                 => 'SI',
				'siracusa'              => 'SR',
				'sondrio'               => 'SO',
				'taranto'               => 'TA',
				'teramo'                => 'TE',
				'terni'                 => 'TR',
				'torino'                => 'TO',
				'trapani'               => 'TP',
				'trento'                => 'TN',
				'treviso'               => 'TV',
				'trieste'               => 'TS',
				'udine'                 => 'UD',
				'varese'                => 'VA',
				'venezia'               => 'VE',
				'verbania-cusio-ossola' => 'VB',
				// This derivation doesn't appear in the list, but seems obvious
				'verbania cusio ossola' => 'VB',
				'vercelli'              => 'VC',
				'verona'                => 'VR',
				'vibo valentia'         => 'VV',
				'vicenza'               => 'VI',
				'viterbo'               => 'VT'
			);
		} elseif ( 'JP' == $this->_country ) {
			$translation_table = array(
				'hokkaido'      => 'JP01',
				'aomori-ken'    => 'JP02',
				'iwate-ken'     => 'JP03',
				'miyagi-ken'    => 'JP04',
				'akita-ken'     => 'JP05',
				'yamagata-ken'  => 'JP06',
				'fukushima-ken' => 'JP07',
				'ibaraki-ken'   => 'JP08',
				'tochigi-ken'   => 'JP09',
				'gunma-ken'     => 'JP10',
				'saitama-ken'   => 'JP11',
				'chiba-ken'     => 'JP12',
				'tokyo-to'      => 'JP13',
				'kanagawa-ken'  => 'JP14',
				'niigata-ken'   => 'JP15',
				'toyama-ken'    => 'JP16',
				'ishikawa-ken'  => 'JP17',
				'fukui-ken'     => 'JP18',
				'yamanashi-ken' => 'JP19',
				'nagano-ken'    => 'JP20',
				'gifu-ken'      => 'JP21',
				'shizuoka-ken'  => 'JP22',
				'aichi-ken'     => 'JP23',
				'mie-ken'       => 'JP24',
				'shiga-ken'     => 'JP25',
				'kyoto-fu'      => 'JP26',
				'osaka-fu'      => 'JP27',
				'hyogo-ken'     => 'JP28',
				'nara-ken'      => 'JP29',
				'wakayama-ken'  => 'JP30',
				'tottori-ken'   => 'JP31',
				'shimane-ken'   => 'JP32',
				'okayama-ken'   => 'JP33',
				'hiroshima-ken' => 'JP34',
				'yamaguchi-ken' => 'JP35',
				'tokushima-ken' => 'JP36',
				'kagawa-ken'    => 'JP37',
				'ehime-ken'     => 'JP38',
				'kochi-ken'     => 'JP39',
				'fukuoka-ken'   => 'JP40',
				'saga-ken'      => 'JP41',
				'nagasaki-ken'  => 'JP42',
				'kumamoto-ken'  => 'JP43',
				'oita-ken'      => 'JP44',
				'miyazaki-ken'  => 'JP45',
				'kagoshima-ken' => 'JP46',
				'okinawa-ken'   => 'JP47',
			);
		} elseif ( 'MX' == $this->_country ) {
			$translation_table = array(
				'ags'   => 'AG',
				'bc'    => 'BC',
				'bcs'   => 'BS',
				'camp'  => 'CM',
				'chis'  => 'CS',
				'chih'  => 'CH',
				'cdmx'  => 'DF', // Both cdmx and df are mapped to DF
				'coah'  => 'CO',
				'col'   => 'CL',
				'df'    => 'DF', // Both cdmx and df are mapped to DF
				'dgo'   => 'DG',
				'mex'   => 'MX',
				'gto'   => 'GT',
				'gro'   => 'GR',
				'hgo'   => 'HG',
				'jal'   => 'JA',
				'mich'  => 'MI',
				'mor'   => 'MO',
				'nay'   => 'NA',
				'nl'    => 'NL',
				'oax'   => 'OA',
				'pue'   => 'PU',
				'qro'   => 'QT',
				'q roo' => 'QR',
				'slp'   => 'SL',
				'sin'   => 'SI',
				'son'   => 'SO',
				'tab'   => 'TB',
				'tamps' => 'TM',
				'tlax'  => 'TL',
				'ver'   => 'VE',
				'yuc'   => 'YU',
				'zac'   => 'ZA',
			);
		} elseif ( 'NL' == $this->_country ) {
			$translation_table = array(
				'drenthe'       => 'DR',
				'flevoland'     => 'FL',
				'friesland'     => 'FR',
				'gelderland'    => 'GE',
				'groningen'     => 'GR',
				'limburg'       => 'LI',
				'noord-brabant' => 'NB',
				'noord-holland' => 'NH',
				'overijssel'    => 'OV',
				'utrecht'       => 'UT',
				'zeeland'       => 'ZE',
				'zuid-holland'  => 'ZH'
			);
		} elseif ( 'TH' == $this->_country ) {
			$translation_table = array(
				'amnat charoen'            => 'TH-37',
				'ang thong'                => 'TH-15',
				'phra nakhon si ayutthaya' => 'TH-14',
				'bangkok'                  => 'TH-10',
				'bueng kan'                => 'TH-38',
				'buri ram'                 => 'TH-31',
				'chachoengsao'             => 'TH-24',
				'chai nat'                 => 'TH-18',
				'chaiyaphum'               => 'TH-36',
				'chanthaburi'              => 'TH-22',
				'chiang mai'               => 'TH-50',
				'chiang rai'               => 'TH-57',
				'chon buri'                => 'TH-20',
				'chumphon'                 => 'TH-86',
				'kalasin'                  => 'TH-46',
				'kamphaeng phet'           => 'TH-62',
				'kanchanaburi'             => 'TH-71',
				'khon kaen'                => 'TH-40',
				'krabi'                    => 'TH-81',
				'lampang'                  => 'TH-52',
				'lamphun'                  => 'TH-51',
				'loei'                     => 'TH-42',
				'lop buri'                 => 'TH-16',
				'mae hong son'             => 'TH-58',
				'maha sarakham'            => 'TH-44',
				'mukdahan'                 => 'TH-49',
				'nakhon nayok'             => 'TH-26',
				'nakhon pathom'            => 'TH-73',
				'nakhon phanom'            => 'TH-48',
				'nakhon ratchasima'        => 'TH-30',
				'nakhon sawan'             => 'TH-60',
				'nakhon si thammarat'      => 'TH-80',
				'nan'                      => 'TH-55',
				'narathiwat'               => 'TH-96',
				'nong bua lamphu'          => 'TH-39',
				'nong khai'                => 'TH-43',
				'nonthaburi'               => 'TH-12',
				'pathum thani'             => 'TH-13',
				'pattani'                  => 'TH-94',
				'phang nga'                => 'TH-82',
				'phatthalung'              => 'TH-93',
				'phayao'                   => 'TH-56',
				'phetchabun'               => 'TH-67',
				'phetchaburi'              => 'TH-76',
				'phichit'                  => 'TH-66',
				'phitsanulok'              => 'TH-65',
				'phrae'                    => 'TH-54',
				'phuket'                   => 'TH-83',
				'prachin buri'             => 'TH-25',
				'prachuap khiri khan'      => 'TH-77',
				'ranong'                   => 'TH-85',
				'ratchaburi'               => 'TH-70',
				'rayong'                   => 'TH-21',
				'roi et'                   => 'TH-45',
				'sa kaeo'                  => 'TH-27',
				'sakon nakhon'             => 'TH-47',
				'samut prakan'             => 'TH-11',
				'samut sakhon'             => 'TH-74',
				'samut songkhram'          => 'TH-75',
				'saraburi'                 => 'TH-19',
				'satun'                    => 'TH-91',
				'sing buri'                => 'TH-17',
				'si sa ket'                => 'TH-33',
				'songkhla'                 => 'TH-90',
				'sukhothai'                => 'TH-64',
				'suphan buri'              => 'TH-72',
				'surat thani'              => 'TH-84',
				'surin'                    => 'TH-32',
				'tak'                      => 'TH-63',
				'trang'                    => 'TH-92',
				'trat'                     => 'TH-23',
				'ubon ratchathani'         => 'TH-34',
				'udon thani'               => 'TH-41',
				'uthai thani'              => 'TH-61',
				'uttaradit'                => 'TH-53',
				'yala'                     => 'TH-95',
				'yasothon'                 => 'TH-35',
				//'phatthaya'              => '',
			);
		} elseif ( 'US' == $this->_country ) {
			$translation_table = array(
				'alabama'                                 => 'AL',
				'alaska'                                  => 'AK',
				'arizona'                                 => 'AZ',
				'arkansas'                                => 'AR',
				'california'                              => 'CA',
				'colorado'                                => 'CO',
				'connecticut'                             => 'CT',
				'deleware'                                => 'DE',
				'district of columbia (washington, d.c.)' => 'DC',
				// The next several derivations don't show up in the list, but seem obvious
				'district of columbia'                    => 'DC',
				'washington, d.c.'                        => 'DC',
				'washington d.c.'                         => 'DC',
				'washington, dc'                          => 'DC',
				'washington dc'                           => 'DC',
				'washington, d. c.'                       => 'DC',
				'washington d. c.'                        => 'DC',
				'washington, d c'                         => 'DC',
				'washington d c'                          => 'DC',
				'florida'                                 => 'FL',
				'georgia'                                 => 'GA',
				'hawaii'                                  => 'HI',
				'idaho'                                   => 'ID',
				'illinois'                                => 'IL',
				'indiana'                                 => 'IN',
				'iowa'                                    => 'IA',
				'kansas'                                  => 'KS',
				'kentucky'                                => 'KY',
				'louisiana'                               => 'LA',
				'maine'                                   => 'ME',
				'maryland'                                => 'MD',
				'massachusetts'                           => 'MA',
				'michigan'                                => 'MI',
				'minnesota'                               => 'MN',
				'mississippi'                             => 'MS',
				'missouri'                                => 'MO',
				'montana'                                 => 'MT',
				'nebraska'                                => 'NE',
				'nevada'                                  => 'NV',
				'new hampshire'                           => 'NH',
				'new jersey'                              => 'NJ',
				'new mexico'                              => 'NM',
				'new jersey'                              => 'NJ',
				'new mexico'                              => 'NM',
				'new york'                                => 'NY',
				'north carolina'                          => 'NC',
				'north dakota'                            => 'ND',
				'ohio'                                    => 'OH',
				'oklahoma'                                => 'OK',
				'oregon'                                  => 'OR',
				'pennsylvania'                            => 'PA',
				'puerto rico'                             => 'PR',
				'rhode island'                            => 'RI',
				'south carolina'                          => 'SC',
				'south dakota'                            => 'SD',
				'tennessee'                               => 'TN',
				'texas'                                   => 'TX',
				'utah'                                    => 'UT',
				'vermont'                                 => 'VT',
				'virginia'                                => 'VA',
				'washington'                              => 'WA',
				'west virginia'                           => 'WV',
				'wisconsin'                               => 'WI',
				'wyoming'                                 => 'WY',
				'armed forces americas'                   => 'AA',
				'armed forces'                            => 'AE',
				'armed forces pacific'                    => 'AP',
				'american samoa'                          => 'AS',
				'guam'                                    => 'GU',
				'northern mariana islands'                => 'MP',
				'virgin islands'                          => 'VI',
				// The next few derivations don't show up on the list, but seem obvious
				'us virgin islands'                       => 'VI',
				'u.s. virgin islands'                     => 'VI',
				'u s virgin islands'                      => 'VI',
				'u. s. virgin islands'                    => 'VI'
			);
		}

		if ( array_key_exists( strtolower( trim( $this->_state ) ), $translation_table ) ) {
			$this->_state = $translation_table[ strtolower( trim( $this->_state ) ) ];
		}

	}

	public function normalizeZip() {
		// TODO: Try to do some ZIP code normalization
	}

	public function getAddressParams( $prefix = '' ) {
		$params = array(
			$prefix . 'NAME' => $this->_name,
			$prefix . 'STREET' => $this->_street1,
			$prefix . 'STREET2' => $this->_street2,
			$prefix . 'CITY' => $this->_city,
			$prefix . 'STATE' => $this->_state,
			$prefix . 'ZIP' => $this->_zip,
			$prefix . 'COUNTRYCODE' => $this->_country,
			$prefix . 'PHONENUM' => $this->_phoneNumber,
		);

		return $params;
	}

	public function loadFromGetECResponse( $getECResponse, $prefix, $isBillingAddress = false ) {
		$map = array(
			'NAME'          => '_name',
			'STREET'        => '_street1',
			'STREET2'       => '_street2',
			'CITY'          => '_city',
			'STATE'         => '_state',
			'ZIP'           => '_zip',
			'PHONENUM'      => '_phoneNumber',
			'ADDRESSSTATUS' => '_addressStatus',
			'ADDRESSOWNER'  => '_addressOwner'
		);

		if ( $isBillingAddress ) {
			$map['COUNTRY'] = '_country';
		} else {
			$map['COUNTRYCODE'] = '_country';
		}

		$found_any = false;

		foreach ( $map as $index => $value ) {
			$var_name = $prefix . $index;
			if ( array_key_exists( $var_name, $getECResponse ) ) {
				$this->$value = $getECResponse[ $var_name ];
				// ADDRESSSTATUS is returned whether or not a billing address is requested, so we don't want
				// the presence of this variable alone be enough to trigger recognition of a complete
				// billing address.
				if ( 'ADDRESSSTATUS' != $index || ! $isBillingAddress ) {
					$found_any = true;
				}
			}
		}

		// After the state has been set, attempt to normalize (in case it comes from a PayPal response)
		$this->normalizeState();

		return $found_any;
	}

	/*
	 * Checks to see if the PayPal_Address object has all the
	 * required parameters when using a shipping address.
	 *
	 * The required parameters for a DoReferenceTransaction call are listed here:
	 * https://developer.paypal.com/docs/classic/api/merchant/DoReferenceTransaction-API-Operation-NVP/#ship-to-address-fields
	 *
	 * Other API operations have the same shipping parameter requirements.
	 * Here's a non-exhaustive list:
	 *
	 * DoExpressCheckoutPayment
	 * https://developer.paypal.com/docs/classic/api/merchant/DoExpressCheckoutPayment-API-Operation-NVP/
	 *
	 * SetExpressCheckout
	 * https://developer.paypal.com/docs/classic/api/merchant/SetExpressCheckout-API-Operation-NVP/
	 *
	 * GetExpressCheckoutDetails
	 * https://developer.paypal.com/docs/classic/api/merchant/GetExpressCheckoutDetails-API-Operation-NVP/
	 */
	public function has_all_required_shipping_params() {
		$has_name    = ! empty( $this->getName() );
		$has_street1 = ! empty( $this->getStreet1() );
		$has_city    = ! empty( $this->getCity() );
		$has_country = ! empty( $this->getCountry() );
		$has_zip     = ! empty( $this->getZip() );
		$has_state   = ! empty( $this->getState() );

		// If the country is the US, a zipcode is required
		$has_zip_if_required = (
			'US' === $this->getCountry()
			? $has_zip
			: true
		);

		// A state is required is the country is one of
		// Argentina, Brazil, Canada, China, Indonesia,
		// India, Japan, Mexico, Thailand or USA
		$has_state_if_required = (
			in_array(
				$this->getCountry(),
				array(
					'AR', // Argentina
					'BR', // Brazil
					'CA', // Canada
					'CN', // China
					'ID', // Indonesia
					'IN', // India
					'JP', // Japan
					'MX', // Mexico
					'TH', // Thailand
					'US', // USA
				),
				true
			)
			? $has_state
			: true
		);

		return (
			$has_name
			&& $has_street1
			&& $has_city
			&& $has_country
			&& $has_zip_if_required
			&& $has_state_if_required
		);

	}
}
