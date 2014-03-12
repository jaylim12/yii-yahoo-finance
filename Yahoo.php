<?php

class Yahoo extends CApplicationComponent
{
	const TYPE_ASSOC = 1;
	const TYPE_NUM = 2;
	public $url = "http://download.finance.yahoo.com/d/quotes.csv";
	public $query_url = "http://d.yimg.com/aq/autoc?callback=YAHOO.util.ScriptNodeDataSource.callbacks";
	public $history_url = "http://ichart.finance.yahoo.com/table.csv";
	public $chartapi_url = "http://chartapi.finance.yahoo.com/instrument/1.0/{symbol}/chartdata;type=quote;range={range}/json/";
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
		$file = $this->exec_curl($this->getUrl());
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
					'timestamp'=>strtotime($data[3] . ' ' . $data[4]),
					'gmtoffset'=>18000,
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
				'todaychange'=>preg_replace('/.+- /', '', $data[15]),
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
		$result = $this->exec_curl($this->getQueryUrl($string));
		$result = str_replace('YAHOO.util.ScriptNodeDataSource.callbacks', '', $result);
		$result = substr($result, 1, -1);
		return CJSON::decode($result);
	}

	public function getHistory($symbol, array $param = array())
	{
		$attributes = array('start' => date("Y-m-d", strtotime(date("Y-m-d") . " -1week")), 'end' => date("Y-m-d"));
		foreach ($param as $key => $value) $attributes[$key] = $value;
		$result = array();
		$file = $this->exec_curl($this->getHistoryUrl(array_merge(array('symbol'=>$symbol), $attributes)));

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
				'change' => $this->getChange($data[1], $data[4]),
				);
			$result[] = $arr;
		}
		return $result;
	}

	public function getChartApi($symbol, $range)
	{
		$result = $this->exec_curl($this->getChartApiUrl($symbol, $range));
		$result = str_replace('finance_charts_json_callback', '', $result);
		$result = substr($result, 1, -1);

		if (preg_match('/errorid:/', $result))
			throw new Exception('API call error', 601);

		return CJSON::decode($result);
	}

	protected function exec_curl($url)
	{
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_AUTOREFERER => true,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:5.0) Gecko/20110619 Firefox/5.0',
			CURLOPT_HTTPGET => true,
			CURLOPT_URL => $url,
			));
		$result = curl_exec($curl);
		$error = curl_errno($curl);
		$error_message = '';

		if ($error)
			$error_message = curl_error($curl);

		$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);

		if ($error || $http_code != '200')
			throw new Exception($error_message ? $error_message : $http_code, $http_code);

		return $result;
	}

	protected function getChange($open,$close)
	{
		if ($open == 0)
			return '0%';
		$change = round(($close-$open) / $open * 100, 2);

		if ($change > 0)
			$change = '+'.$change;

		return $change.'%';
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
		$attributes = array('symbol' => null, 'start' => null, 'end' => null);
		foreach ($param as $key => $value) $attributes[$key] = $value;
		$start = new DateTime($attributes['start']);
		$end = new DateTime($attributes['end']);
		$http_data = array(
			's' => $attributes['symbol'],
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

	protected function getChartApiUrl($symbol, $range)
	{
		$available_range = array('1d', '2d', '3d', '4d', '5d', '6d', '7d', '8d', '9d', '10d');
		if (!in_array($range, $available_range))
			throw new Exception("Invalid range");

		return str_replace(array('{symbol}', '{range}'), array($symbol, $range), $this->chartapi_url);
	}
}
