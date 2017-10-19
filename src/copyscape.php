<?php
declare(strict_types=1);


namespace kdaviesnz\copyscape;


class Copyscape implements ICopyscape
{
    private $copyscape_xml_data = array();
    private $copyscape_xml_depth = 0;
    private $copyscape_xml_ref = array();
    private $copyscape_xml_spec;

    private $result;

    /**
     * Copyscape constructor.
     * @param array $copyscape_xml_data
     */
    public function __construct(string $url, array $settings)
    {
       $this->settings = $settings;
       $this->result = json_encode($this->run($url));
    }

    public function __toString()
    {
       return $this->result;
    }

    public function run( $permalink ) {

        $result = array();

		$copyscape_result = $this->search_internet( $permalink );

        $webpage_count = 0;
        $total_words = 0;
        $unique_word_percent = 0.00;


		if( !empty( $copyscape_result ) && isset( $copyscape_result['count'] ) ) {

            $webpage_count = $copyscape_result['count'];
            $total_words = $copyscape_result['querywords'];

			foreach( $copyscape_result['result'] as $solo_res_cs)
			{
				$all_arr_matched_wrds[] = $solo_res_cs['minwordsmatched'];
			}

			$sum_all_arr_matched_wrds = array_sum( $all_arr_matched_wrds );
			$average_all_arr_matched_wrds = $sum_all_arr_matched_wrds / $webpage_count;
			$unique_word_percent = ( $average_all_arr_matched_wrds / $total_words ) * 100;

			$unique_word_percent = number_format( $unique_word_percent, 2) ;

            $result = array(
                "status"=>'success',
                'results'=> array(
                'webpage_count' => $webpage_count,
                'total_words' => $total_words,
                'unique_word_perc' => $unique_word_percent,
                )
            );

		} elseif ( isset( $copyscape_result['error'] ) ) {
            $result = array(
                'status'=>"failure",
                'error'=>$copyscape_result['error']
            );
        }

		return $result;

	}

    private function search_internet( $permalink, $full = null ) {
        $result = $this->search( $permalink, $full, $operation = 'csearch' );

        return $result;
    }

	private function search( $permalink, $full=null, $operation='csearch')  {

        $params['q'] = $permalink;

        if ( isset( $full ) ) {
            $params['c'] = $full;
        }

        $result = $this->api( $operation, $params, array( 2 => array('result' => 'array' ) ) );

        return $result;

	}

    private function api( $operation, $params=array(), $xmlspec=null, $postdata=null ) {

        $url = 'http://www.copyscape.com/api/?u='.urlencode( $this->settings["copyscape_user_name"] ).
            '&k='.urlencode( $this->settings["copyscape_api_key"] ).'&o='.urlencode( $operation );

        foreach ($params as $name => $value)
            $url .= '&' . urlencode( $name ) . '=' . urlencode( $value );

        $curl=curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, isset( $postdata ) );

        if ( isset( $postdata ) ) {
            curl_setopt( $curl, CURLOPT_POSTFIELDS, $postdata );
        }

        $response = curl_exec( $curl );
        curl_close($curl);

        if ( strlen( $response ) ) {
            return $this->read_xml( $response, $xmlspec );
        }
        else {
            return array();
        }
    }

    private function read_xml( $xml, $spec = array() )  {

        $this->copyscape_xml_data = array();
        $this->copyscape_xml_depth = 0;
        $this->copyscape_xml_ref= array();
        $this->copyscape_xml_spec = $spec;

        $parser= xml_parser_create();

        xml_set_element_handler( $parser, array( $this, 'copyscape_xml_start' ) , array( $this, 'copyscape_xml_end') ) ;
        xml_set_character_data_handler( $parser, array( $this, 'copyscape_xml_data' ) );


        if ( ! xml_parse( $parser, $xml, true ) ) {
            return array();
        }

        xml_parser_free( $parser );

        return $this->copyscape_xml_data;
    }

    private function copyscape_xml_start( $parser, $name, $attribs ) {

        $this->copyscape_xml_depth++;

        // $name = response
        $name = strtolower( $name );

        // 1, 2
        if ( $this->copyscape_xml_depth == 1) {
            $this->copyscape_xml_ref[$this->copyscape_xml_depth] =& $this->copyscape_xml_data;
        }
        else {

            if ( ! is_array( $this->copyscape_xml_ref[$this->copyscape_xml_depth-1] ) ) {
                $this->copyscape_xml_ref[$this->copyscape_xml_depth - 1] = array();
            }

            if ( @$this->copyscape_xml_spec[$this->copyscape_xml_depth][$name] == 'array' ) {
                if ( ! is_array( @$this->copyscape_xml_ref[$this->copyscape_xml_depth-1][$name] ) ) {
                    $this->copyscape_xml_ref[$this->copyscape_xml_depth-1][$name] = array();
                    $key=0;
                } else {
                    $key = 1 + max( array_keys( $this->copyscape_xml_ref[$this->copyscape_xml_depth - 1][$name] ) );
                }

                $this->copyscape_xml_ref[$this->copyscape_xml_depth -1 ][$name][$key]='';
                $this->copyscape_xml_ref[$this->copyscape_xml_depth] =& $copyscape_xml_ref[$this->copyscape_xml_depth-1][$name][$key];

            } else {
                $this->copyscape_xml_ref[$this->copyscape_xml_depth-1][$name]='';
                $this->copyscape_xml_ref[$this->copyscape_xml_depth] =& $this->copyscape_xml_ref[$this->copyscape_xml_depth -1][$name];
            }
        }
    }

    private function copyscape_xml_end( $parser, $name ) {
        unset( $this->copyscape_xml_ref[$this->copyscape_xml_depth]);
        $this->copyscape_xml_depth--;
    }

    private function copyscape_xml_data($parser, $data) {

        if ( is_string( $this->copyscape_xml_ref[$this->copyscape_xml_depth])) {
            $this->copyscape_xml_ref[$this->copyscape_xml_depth] .= $data;
        }
    }

}

