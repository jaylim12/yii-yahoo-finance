<?php

class Yahoo extends CApplicationComponent
{
	const TYPE_ASSOC = 1;
	const TYPE_NUM = 2;
	public $url = "http://download.finance.yahoo.com/d/quotes.csv";
	public $query_url = "http://d.yimg.com/aq/autoc?callback=YAHOO.util.ScriptNodeDataSource.callbacks";
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
		$url = $this->getUrl();
		$handle = fopen($url, "r");

		if (empty($handle))
			throw new Exception("Fail to open Yahoo url", 101);

		while ($data = fgetcsv($handle)) {
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
		fclose($handle);
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
}
