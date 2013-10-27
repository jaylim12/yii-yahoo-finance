<?php

class Yahoo extends CApplicationComponent
{
	const TYPE_ASSOC = 1;
	const TYPE_NUM = 2;
	public $url = "http://download.finance.yahoo.com/d/quotes.csv";
	public $query_url = "http://d.yimg.com/aq/autoc?callback=YAHOO.util.ScriptNodeDataSource.callbacks";
	public $history_url = "http://ichart.finance.yahoo.com/table.csv";
	public $fields = array();

	public function init()
	{
		$default = array(
			'f' => 'snl1d1t1c1ohgvb2m2wjkk2j6pb3',
			);
		$this->fields = $default;
	}

	public function getQuotes($quotes, $result_type = self::TYPE_ASSOC)
	{
		list($query, $result) = array($quotes, array());
		if (is_array($quotes))
			$query = implode(',', $quotes);

		$this->fields['s'] = $query;
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_AUTOREFERER => true,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT => 10,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:5.0) Gecko/20110619 Firefox/5.0',
			CURLOPT_HTTPGET => true,
			CURLOPT_URL => $this->getUrl(),
			));
		$file = curl_exec($curl);
		$error = curl_errno($curl);
		$error_message = '';

		if ($error)
			$error_message = curl_error($curl);

		$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);

		if ($error || $http_code != '200')
			throw new Exception($error_message ? $error_message : $http_code, $http_code);

		$lines = explode("\n", $file);
		foreach ($lines as $index => $line) {
			$data = str_getcsv($line);
			if (count($data) != 19) continue ;
			$arr = array(
				'quote'=>$data[0],
				'name'=>$data[1],
				'lastTrade'=>array(
					'index'=>$data[2],
					'date'=>$data[3],
					'time'=>$data[4],
					),
				'change'=>$data[5],
				'open'=>$data[6],
				'highest'=>$data[7],
				'lowest'=>$data[8],
				'volume'=>$data[9],
				'ask'=>$data[10],
				'DRange'=>$data[11],
				'52WRange'=>$data[12],
				'52lowest'=>$data[13],
				'52highest'=>$data[14],
				'todaychange'=>preg_replace('/.+-/', '', $data[15]),
				'52change'=>$data[16],
				'previous'=>$data[17],
				'bid'=>$data[18],
				);
			if ($result_type == self::TYPE_ASSOC)
				$result[$data[0]] = $arr;
			else
				$result[] = $arr;
		}
		return $result;
	}

	public function find($string)
	{
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_AUTOREFERER => true,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT => 10,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:5.0) Gecko/20110619 Firefox/5.0',
			CURLOPT_HTTPGET => true,
			CURLOPT_URL => $this->getQueryUrl($string),
			));
		$result = curl_exec($curl);
		$error = curl_errno($curl);
		$error_message = '';

		if ($error)
			$error_message = curl_error($curl);

		curl_close($curl);

		if ($error)
			throw new Exception($error_message);

		$result = str_replace('YAHOO.util.ScriptNodeDataSource.callbacks', '', $result);
		$result = substr($result, 1, -1);
		return CJSON::decode($result);
	}

	public function getHistory($ticker, array $param = array())
	{
		$attributes = array('start' => date("Y-m-d", strtotime(date("Y-m-d") . " -1week")), 'end' => date("Y-m-d"));
		foreach ($param as $key => $value) $attributes[$key] = $value;
		$result = array();
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_AUTOREFERER => true,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT => 10,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:5.0) Gecko/20110619 Firefox/5.0',
			CURLOPT_HTTPGET => true,
			CURLOPT_URL => $this->getHistoryUrl(array_merge(array('ticker'=>$ticker), $attributes)),
			));
		$file = curl_exec($curl);
		$error = curl_errno($curl);
		$error_message = '';

		if ($error)
			$error_message = curl_error($curl);

		$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);

		if ($error || $http_code != '200')
			throw new Exception($error_message ? $error_message : $http_code, $http_code);

		$lines = explode("\n", $file);
		foreach ($lines as $index => $line) {
			if ($index == 0) continue ;
			$data = str_getcsv($line);
			if (count($data) != 7) continue ;
			$arr = array(
				'date' => $data[0],
				'open' => $data[1],
				'high' => $data[2],
				'low' => $data[3],
				'close' => $data[4],
				'volume' => $data[5],
				'adj_close' => $data[6],
				);
			$result[] = $arr;
		}
		return $result;
	}

	protected function getUrl()
	{
		$param = array();
		foreach ($this->fields as $key => $value)
			$param[] = $key . "=" . $value;

		if (empty($param))
			return $this->url;
		return $this->url . "?" . implode("&", $param);
	}

	protected function getQueryUrl($string)
	{
		$string = urlencode($string);
		return $this->query_url . '&query=' . $string;
	}

	protected function getHistoryUrl(array $param)
	{
		$attributes = array('ticker' => null, 'start' => null, 'end' => null);
		foreach ($param as $key => $value) $attributes[$key] = $value;
		$start = new DateTime($attributes['start']);
		$end = new DateTime($attributes['end']);
		$http_data = array(
			's' => $attributes['ticker'],
			'd' => $end->format('n') - 1,
			'e' => $end->format('j'),
			'f' => $end->format('Y'),
			'g' => 'd',
			'a' => $start->format('n') - 1,
			'b' => $start->format('j'),
			'c' => $start->format('Y'),
			'ignore' => '.csv',
			);
		return $this->history_url . '?' . http_build_query($http_data);
	}
}
