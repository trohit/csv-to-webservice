<?php error_reporting(0);?>
<?php
/*
  CSV to Webservice by Christian Heilmann
  Homepage: http://isithackday.com/csvtowebservice/index.php
  Copyright (c)2010 Christian Heilmann
  Code licensed under the BSD License:
  http://wait-till-i.com/license.txt

  Options:

  filter :	contains an array of fields to not show in the form or the 
  table. This allows you to get rid of some parts of the data.

  rename:	some fields have ugly names. renames them in the form

  preset : 	is an array of fields to preset with a hard value. These fields 
  will be part of the query of the data but will not be added to the form or 
  displayed. This allows you to pre-filter the data.

  prefill:	an array of fields to pre-fill the form with in case you want 
  to give the end user a hint what they can search for.

  uppercase:	boolean value if the script should uppercase the first letter 
  of the field name or not.

  choices:	when you want a combo of values for a particular field.

  notinform:	do not show in form but are shown in result.

  grep_specific:instead of like, do an exact match for that value.


  Code Snippet changes for index.php:

<?php 
  include('csvtoservice.php');
  $content = csvtoservice(
    'http://winterolympicsmedals.com/medals.csv',
    array(
      'filter'=> array('city'),
      'rename'=> array(
        'noc'=>'country'
      ),
      'preset'=> array(
        'year'=> '1992'
      ),
      'prefill'=> array(
        'discipline'=> 'Alpine Skiing',
        'medal'=> 'Gold'
      ),
      'uppercase'=>true,
      'choices'=> array(
	      'medal'=> array(
		      "Gold",
		      "Silver",
		      "Bronze",
	      ),
      ),
      'notinform'=> array(
	      'eventgender',
      ),
      'grep_specific'=> array(
	      'sport',
      ),
    )
 );
 */
function csvtoservice($url,$options){
	$csv = get($url);
	$lines = preg_split('/\r?\n/msi',$csv);
	$columns = split(',',
		strtoLower(
			preg_replace('/\s/','',$lines[0])
		)
	);
	$colstring = join(',',$columns);

	if($options['preset']){
		$pres = $options['preset'];
		foreach(array_keys($pres) as $p){
			$presetstring .= ' and '.$p.' like "%'.$pres[$p].'%"'; 
		}
		$columns = array_diff($columns,array_keys($pres));
	}

	if($options['filter']){
		$columns = array_diff($columns,$options['filter']);
	}


	if($options['prefill']){
		foreach(array_keys($options['prefill']) as $p){
			$_GET[$p] = $options['prefill'][$p];
		}
	}

	if($options['rename']){
		$renames = array_keys($options['rename']); 
		foreach($columns as $k=>$c){
			foreach($renames as $r){
				if(!in_array($c,$renames)){
					$displaycolumns[$k] = $c;
				} else {
					if($c == $r){
						$displaycolumns[$k] = $options['rename'][$r];
					}
				}
			}
		}
	} else {
		$displaycolumns = $columns;
	}

	if($options['choices']){
		$choosyfields = array_keys($options['choices']); 
	}

	foreach($columns as $c){
		filter_input(INPUT_GET, $c, FILTER_SANITIZE_SPECIAL_CHARS);    
		$fromget[$c] = $_GET[$c];
	}

	$current = preg_replace('/.*\/+/','',$_SERVER['PHP_SELF']);

	$csvform = '<form action="'.$current.'">';
	foreach($columns as $k=>$c){
		if (in_array($c, $options['notinform'])) {
			/*
			 * dont show this field in search form,
			 * only show it in results.
			 */
			continue;
		}

		if (in_array($c, $choosyfields)) {
			$csvform .= '<div><label for="'.$c.'">'.
				($options['uppercase'] ? 
				ucfirst($displaycolumns[$k]) :
				$displaycolumns[$k]).
				'</label>';
			$csvform .= '<select name="'.$c.'" id="'.$c.'">';

			foreach($options['choices'][$c] as $o =>$v) {
				$csvform .= '<option value="'.$v.'">'.$v.'</option>';
			}
			$csvform .= '</select>';
			$csvform .= '</div>';
		} else {
			$csvform .= '<div><label for="'.$c.'">'.
				($options['uppercase'] ? 
				ucfirst($displaycolumns[$k]) :
				$displaycolumns[$k]).
				'</label>'.
				'<input type="text" id="'.$c.'" name="'.$c.
				'" value="'.$fromget[$c].'"></div>';
		}
	}
	$csvform .= '<div id="bar"><input type="submit" name="csvsend"'.
		' value="search"></div>';
	$csvform .= '</form>';

	if(isset($_GET['csvsend'])){
		$yql = 'select * from csv where url="'.$url.'" '.
			'and columns="'.$colstring.'"';

		foreach($columns as $c){
			if(isset($_GET[$c]) && $_GET[$c]!='' && $_GET[$c]!="ANY"){
				if (in_array($c, $options['grep_specific'])) {
					$yql .= ' and '.$c.' = '.$_GET[$c];
				} else {
					$yql .= ' and '.$c.' like "%'.$_GET[$c].'%"';
				}
			}
		}
		$yql .= $presetstring;
		$yqlquery = '<div id="yql">'.$yql.'</div>';

		$yqlendpoint = 'http://query.yahooapis.com/v1/public/yql?format=json';
		$query = $yqlendpoint.'&q='.urlencode($yql);
		$data = get($query);
		$datadecoded = json_decode($data);

		$csvtable = '<table><thead><tr>';
		foreach($columns as $k=>$c){
			$csvtable .= '<th scope="col">'.
				($options['uppercase'] ? 
				ucfirst($displaycolumns[$k]) :
				$displaycolumns[$k]).
				'</th>';
		}
		$csvtable .= '</tr></thead><tbody>';
		if(count($datadecoded->query->results->row) == 1){
			#print "singular case\n";
			$csvtable .= '<tr>';
			$select_fields = array_keys(get_object_vars($datadecoded->query->results->row));
			foreach ($select_fields as $s){
				if (in_array($s, $columns)) {
					$csvtable .= '<td>'.$datadecoded->query->results->row->$s.'</td>';
				}
			}
			$csvtable .= '</tr>';
		} else if(count($datadecoded->query->results->row) > 1){

			foreach ($datadecoded->query->results->row as $r){
				$csvtable .= '<tr>';
				foreach($columns as $c){
					$csvtable .= '<td>'.$r->$c.'</td>';
				}
				$csvtable .= '</tr>';
			}
		} else {
			$csvtable .=  '<tr><td class="error" colspan="'.sizeof($columns).
				'">No results found. Bummer.</td></tr>';
		}
		$csvtable .=  '</tbody></table>';
	}
	$csvtable .= count($datadecoded->query->results->row) . ' matches found<br>';
	$num_matches = count($datadecoded->query->results->row);


	return array(
		'num_matches'=>$num_matches,
		'table'=>$csvtable,
		'form'=>$csvform,
		'query'=>$yqlquery,
		'json'=>$data
	);
}

function get($url){
	$ch = curl_init(); 
	curl_setopt($ch, CURLOPT_URL, $url); 
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	$output = curl_exec($ch); 
	curl_close($ch);
	return $output;
}
