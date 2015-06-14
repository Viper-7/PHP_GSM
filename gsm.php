<?php
/**
 * GSM Modem interface class
 *
 * @author Viper-7 <viper7@viper-7.com
 * @version 1.0.0
 * @license Beerware
 **/
class GSM {
	/**
	 * phpserial Instance
	 *
	 * @access private
	 **/
	protected $serial;
	
	/**
	 * Character set for messages, currently ASCII or GSM (if GsmEncoder class is provided)
	 * @access private
	 **/
	protected $charset;

	/**
	 * Debug display flag
	 *
	 * @access private
	 **/
	protected $debug;
	
	/**
	 * Map to convert from GSM 7-bit charset to ISO-8859-1
	 **/
	protected $gsm_to_iso;

	/**
	 * Map to convert to GSM 7-bit charset from ISO-8859-1
	 **/
	protected $iso_to_gsm;
	
	/**
	 * Map of Code => Friendly Name for Phonebook storage areas
	 * 
	 * @access private
	 **/
	protected $phonebookStores = array(
		'SM' => 'SIM Contacts', 
		'FD' => 'SIM Fixed Dialing', 
		'ME' => 'Device Contacts', 
		'MT' => 'Device + SIM Contacts', 
		'ON' => 'Own Numbers', 
		'EN' => 'Emergency', 
		'LD' => 'Last Dialled', 
		'MC' => 'Missed Calls', 
		'RC' => 'Received Calls', 
		'SN' => 'Network Services'
	);

	/**
	 * Map of Code => Friendly Name for SMS storage areas
	 * 
	 * @access private
	 **/
	protected $smsStores = array(
		'SM' => 'SIM Storage',
		'ME' => 'Device Storage',
		'MT' => 'Device + SIM Storage',
		'BM' => 'Broadcast Message Storage',
		'SR' => 'Status Report Storage',
		'TA' => 'Terminal Adapter Storage',
	);
	
	/**
	 * Default phonebook to read from
	 * 
	 * @access private
	 **/
	protected $defaultPhonebook = 'SM';

	/**
	 * Default message store to read from
	 *
	 * @access private
	 **/
	protected $defaultSMSStore = 'SM';
	
	/**
	 * Lists SMS messages in the specified or default storage
	 *
	 * @param string (unread|unsent|read|sent|all) Message status to filter by
	 * @param string SMS Storage location to use
	 *
	 * @return array
	 *
	 * @example gsmexamples.php 3 5 List the messages on the SIM
	 **/
	public function listSMS($filter=null, $store=null) {
		switch($filter) {
			case 'unread': 	$mode = 'REC UNREAD'; break;
			case 'read': 	$mode = 'REC READ'; break;
			case 'unsent': 	$mode = 'STO UNSENT'; break;
			case 'sent': 	$mode = 'STO SENT'; break;
			default: 		$mode = 'ALL';
		}
		
		if(!$store)
			$store = $this->defaultSMSStore;
		
		if($key = array_search($store, $this->smsStores))
			$store = $key;
		
		$res = $this->send("AT+CPMS=\"{$store}\"");
		if(preg_match('/\+CPMS:\s*(\d+),(\d+)/', $res, $match)) {
			list(,$count,$max) = $match;
			
			if($count == 0)
				return array();
		}
		
		$sms = $this->send("AT+CMGL=\"{$mode}\"", 0.5);
		
		if($sms === true) return array();

		$out = array();
		if($sms) {
			if(preg_match_all('/\+CMGL:\s*(\d+),"([^"\n]*)","([^"\n]*)",(\d*),"?([^"\n]*)"?\s*(.*?)\s*(?=\+CMGL|$)/ms', $sms, $matches, PREG_SET_ORDER)) {
				foreach($matches as $match) {
					list(,$index,$status,$from,$reference,$time,$message) = $match;
					
					if(preg_match('/^[0-9A-F]+$/', $message) && strlen($message) % 2 == 0) {
						$message = hex2bin($message);
					}
					$key = $from . '_' . $time;
				
					if(isset($out[$key])) {
						$out[$key]['Index'][] = $index;
						$out[$key]['Message'] .= $message;
					} else {
						$out[$key] = array(
							'From' => $from,
							'Message' => $message,
							'Status' => $status,
							'Index' => array($index),
							'Reference' => $reference,
							'Time' => $time
						);
					}
				}
			}
		}
		
		return $out;
	}
	
	/**
	 * Lists all SMS storage locations
	 * 
	 * @return array ["Code" => "Friendly Name"]
	 *
	 * @example gsmexamples.php 10 5 List the names of all the message stores
	 **/
	public function listSMSStores() {
		$res = $this->send('AT+CPMS=?');
		$out = array();
		
		if(preg_match('/\+CPMS:\s*\("([^\)]+)"\)/', $res, $match)) {
			list(, $modes) = $match;
			foreach(explode('","', $modes) as $mode) {
				if(isset($this->smsStores[$mode]))
					$out[$mode] = $this->smsStores[$mode];
				else
					throw new GSMException('Unknown SMS Storage ' . $mode);
			}
		}
		
		return $out;
	}

	/**
	 * Lists all SMS messages from all storage locations
	 *
	 * @param string (unread|unsent|read|sent|all) Message status to filter by
	 *
	 * @return array ["Storage Name' => listSMS($store)]
	 *
	 * @example gsmexamples.php 17 7 List all messages on the device
	 **/
	public function listAllSMS($filter=null) {
		$out = array();
		foreach($this->listSMSStores() as $store => $name) {
			if($store == 'MT') continue;
			$out[$name] = $this->listSMS($filter, $store);
		}
		return $out;
	}

	/**
	 * Read an SMS message
	 *
	 * @param int Index of the message to read
	 *
	 * @example gsmexamples.php 26 3 Display the message from an index
	 **/
	public function readSMS($index) {
		$res = $this->send("AT+CMGR={$index}", 0.2);
		if(preg_match('/\+CMGR:\s*"([^"\n]*)","([^"\n]*)",(\d*),"?([^"\n]*)"?\s*(.*?)\s*(?=OK|$)/ms', $res, $match)) {
			list(,$status,$from,$reference,$time,$message) = $match;
			
			$message = hex2bin($message);
			
			return array(
				'From' => $from,
				'Message' => $message,
				'Status' => $status,
				'Index' => $index,
				'Reference' => $reference,
				'Time' => $time
			);
		}
	}
	
	/**
	 * Send an SMS to a number
	 *
	 * @param string Number to send to
	 * @param string Message text
	 * 
	 * @return int Message reference
	 *
	 * @example gsmexamples.php 31 2 Send a simple text message
	 **/
	public function sendSMS($number, $msg) {
		$res = $this->send("AT+CMGS=\"{$number}\"\n{$msg}" . chr(26), 0.4);
		if(preg_match('/\+CMS ERROR:\s*(\d+)/', $res, $match)) {
			throw new GSMException("Sending message failed with code {$match[1]}");
		}
		
		if(preg_match('/\+CMGS:\s*(\d+)$/m', $res, $match)) {
			list(, $reference) = $match;
			return $reference;
		}
	}
	
	/**
	 * Delete an SMS message
	 *
	 * @param string|array ID(s) to delete
	 * @param string Message store to delete from
	 *
	 * @example gsmexamples.php 35 2 Delete a single message
	 * @example gsmexamples.php 39 6 Delete all messages on the device
	 **/
	public function deleteSMS($ids, $store=null) {
		if(!$store)
			$store = $this->defaultSMSStore;
		
		if($key = array_search($store, $this->smsStores))
			$store = $key;
		
		if(!is_array($ids))
			$ids = array($ids);
		
		foreach($ids as $id) {
			$this->send("AT+CPMS=\"{$store}\"");
			$this->send("AT+CMGD={$id}");
		}
	}
	
	/**
	 * Writes an SMS message and stores it in the device
	 *
	 * @param string Number to send to
	 * @param string Message text
	 * @param int Message index to overwrite
	 * @param string Message store to write to
	 *
	 * @return int Message index that was written
	 *
	 * @example gsmexamples.php 48 3 Write an SMS and send it
	 **/
	public function writeSMS($number, $msg, $index=null, $store=null) {
		if($index)
			$this->send("AT+WMGO={$index}");

		if(!$store)
			$store = $this->defaultSMSStore;
		
		if($key = array_search($store, $this->smsStores))
			$store = $key;
			
		$this->send("AT+CPMS=\"{$store}\",\"{$store}\"");
		
		$res = $this->send("AT+CMGW=\"{$number}\"\r\n" . $msg . "\n" . chr(26), 0.5);
		if(preg_match('/\+CMGW:\s*(\d+)/', $res, $match)) {
			list(, $index) = $match;
			return $index;
		}
		
		return false;
	}
	
	/**
	 * Sends an SMS message stored in the device
	 *
	 * @param int Message index to send
	 * @param string Number to send to
	 * @param string Message store to send from
	 *
	 * @example gsmexamples.php 48 3 Write an SMS and send it
	 **/
	public function sendExistingSMS($index, $number=null, $store=null) {
		if($number) $number=", {$number}";

		if(!$store)
			$store = $this->defaultSMSStore;
		
		if($key = array_search($store, $this->smsStores))
			$store = $key;
			
		$this->send("AT+CPMS=\"{$store}\",\"{$store}\"");
		
		$res = $this->send("AT+CMSS={$index}{$number}", 0.5);
		if(preg_match('/\+CMSS:\s*(\d+)/', $res, $match)) {
			list(, $reference) = $match;
			return $reference;
		}
	}
	
	
	/**
	 * Sets the default phonebook for operations
	 *
	 * @param string Phonebook name
	 *
	 * @example gsmexamples.php 87 6 Sets the default phonebook and lists the contacts inside it
	 **/
	public function setDefaultPhonebook($phonebook) {
		if($key = array_search($phonebook, $this->phonebookStores))
			$phonebook = $key;
		
		$this->defaultPhonebook = $phonebook;
	}
	
	/**
	 * Gets the default phonebook for operations
	 *
	 * @return string Phonebook name
	 **/
	public function getDefaultPhonebook() {
		return $this->phonebookStores[$this->defaultPhonebook];
	}
	
	/**
	 * Lists the entries in a phonebook
	 * 
	 * @param string Phonebook name to list
	 *
	 * @return array ["Number" => "+123456", "Name" => "Bob Jones", "Type" => 129]
	 *
	 * @example gsmexamples.php 53 5 Lists the contacts on the SIM
	 **/
	public function listPhonebook($phonebook=null) {
		if(!$phonebook)
			$phonebook = $this->defaultPhonebook;
		
		if($key = array_search($phonebook, $this->phonebookStores))
			$phonebook = $key;
		
		$this->send("AT+CPBS=\"{$phonebook}\"");
		
		$res = $this->send('AT+CPBS?');
		if(preg_match('/\+CPBS:\s*"(\w+)",(\d+),(\d+)/', $res, $match)) {
			list(, $selectedStore, $entries, $limit) = $match;
			
			if($selectedStore != $phonebook)
				throw new GSMException('Failed to select Phonebook ' . $phonebook . (isset($this->phonebookStores[$phonebook]) ? '(' . $this->phonebookStores[$phonebook] . ')' : ''));
			
			if($entries > 0) {
				$res = $this->send("AT+CPBR=1,{$limit}", 0.5);
			} else {
				$res = '';
			}
		} else {
			$res = $this->send('AT+CPBR=?');
			if(preg_match('/\+CPBR:\s*\((\d+)-(\d+)\),(\d+),(\d+)/', $res, $match)) {
				list(,$start,$end,$maxDigits,$maxText) = $match;
				$res = $this->send("AT+CPBR={$start},{$end}", 0.5);
			}
		}
		
		$out = array();
		if($res) {
			foreach(explode("\n", $res) as $entry) {
				if(preg_match('/\+CPBR:\s*(\d+),"([^"]*)",(\d+),"([^"]*)"/', $entry, $match)) {
					list(, $index, $number, $type, $name) = $match;
					$out[] = array('Name'=>$name,'Number'=>$number,'Index'=>$index,'Type'=>$type);
				} elseif(trim($entry) && $entry != 'OK') {
					throw new GSMException('Invalid Phonebook entry ' . $entry);
				}
			}
		}
		
		return $out;
	}

	/**
	 * Lists the available phonebooks
	 *
	 * @return array ["Code" => "Friendly Name"]
	 *
	 * @example gsmexamples.php 67 5 Lists the names of all the phonebooks on the device
	 **/
	public function listPhonebooks() {
		$res = $this->send('AT+CPBS=?', 0.5);
		if(preg_match('/\+CPBS:\s*\("([^\)]+)"\)/', $res, $match)) {
			list(, $modes) = $match;
			$stores = array();
			foreach(explode('","', $modes) as $mode) {
				if(isset($this->phonebookStores[$mode]))
					$stores[$mode] = $this->phonebookStores[$mode];
				else
					throw new GSMException('Unknown Phonebook ' .$mode);
			}
			return $stores;
		}
	}
	
	/**
	 * Lists the entries in all phonebooks
	 *
	 * @return array ["Phonebook Name" => listPhonebook($store)]
	 *
	 * @example gsmexamples.php 74 6 Lists all the contacts on the device
	 **/
	public function listAllPhonebooks() {
		$out = array();
		foreach($this->listPhonebooks() as $phonebook => $name) {
			if($phonebook == 'MT') continue;
			try {
				$out[$name] = $this->listPhonebook($phonebook);
			} catch (GSMException $e) {
			}
			$out[$name] = $this->listPhonebook($phonebook);
		}
		return $out;
	}
	
	/**
	 * Deletes an entry from a phonebook
	 *
	 * @param int Index of the entry to delete
	 * @param string Phonebook to delete from
	 *
	 * @example gsmexamples.php 74 6 Deletes all contacts on the device
	 **/
	public function deletePhonebookEntry($index, $phonebook=null) {
		if(!$phonebook)
			$phonebook = $this->defaultPhonebook;
		
		if($key = array_search($phonebook, $this->phonebookStores))
			$phonebook = $key;
		
		$this->send("AT+CPBS=\"{$phonebook}\"");
		$this->send("AT+CPBW={$index}");
	}
	
	/**
	 * Adds an entry to a phonebook
	 *
	 * @param string Number to store
	 * @param string Name of entry
	 * @param int Type
	 * @param string Phonebook to write to
	 *
	 * @example gsmexamples.php 83 2 Adds a contact to the SIM
	 **/
	public function addPhonebookEntry($number, $name, $type=129, $phonebook=null) {
		if(!$phonebook)
			$phonebook = $this->defaultPhonebook;
		
		if($key = array_search($phonebook, $this->phonebookStores))
			$phonebook = $key;
		
		$this->send("AT+CPBS=\"{$phonebook}\"");
		$this->send("AT+CPBW=,\"{$number}\",{$type},\"{$name}\"");
	}
	
	
	/**
	 * Gets the Manufacturer of the device
	 *
	 * @return string
	 **/
	public function getManufacturer() {
		return $this->send('AT+CGMI');
	}
	
	/**
	 * Gets the Model of the device
	 *
	 * @return string
	 **/
	public function getModel() {
		return $this->send('AT+CGMM');
	}
	
	/**
	 * Gets the Revision of the device
	 *
	 * @return string
	 **/
	public function getRevision() {
		return $this->send('AT+CGMR');
	}
	
	/**
	 * Gets the Serial Number of the device
	 *
	 * @return string
	 **/
	public function getSerialNumber() {
		return $this->send('AT+CGSN');
	}
	
	/**
	 * Gets the Network signal quality
	 *
	 * @return int Signal quality in dBm
	 **/
	public function getSignaldBm() {
		$signal = $this->getSignalInfo();
		
		if(isset($signal['dBm']))
			return $signal['dBm'];
	}

	/**
	 * Gets the Network signal rating
	 *
	 * @return string Signal rating (Poor, OK, Good, Excellent, Unknown)
	 **/
	public function getSignalRating() {
		$signal = $this->getSignalInfo();
		
		if(isset($signal['Rating']))
			return $signal['Rating'];
	}

	/**
	 * Gets the Network error rate
	 *
	 * @return int|string
	 **/
	public function getErrorRate() {
		$signal = $this->getSignalInfo();
		
		if(isset($signal['ErrorRate']))
			return $signal['ErrorRate'];
	}

	/**
	 * Gets a variety of information about the Network signal
	 *
	 * @return array ["Rating" => "Poor,OK,Good,Excellent,Unknown", "dBm" => 103, "ErrorRate" => "Unknown"]
	 **/
	public function getSignalInfo() {
		$res = $this->send('AT+CSQ');
		
		if(preg_match('/\+CSQ: (\d+),(\d+)/', $res, $match)) {
			list(, $rssi, $ber) = $match;
			
			$dBm = $rssi == 99 ? false : 113 - (2 * $rssi);
			$err = $ber == 99 ? false : $bar;
			
			$info = array('dBm' => $dBm, 'ErrorRate' => $err);
			
			if($rssi < 10) $info['Rating'] = 'Poor';
			elseif($rssi < 15) $info['Rating'] = 'OK';
			elseif($rssi < 20) $info['Rating'] = 'Good';
			elseif($rssi < 31) $info['Rating'] = 'Excellent';
			else $info['Rating'] = 'Unknown';
			
			return $info;
		}
	}
	
	/**
	 * Get the current power status & battery level
	 *
	 * @return array ['Level' => 0-100, 'Status' => 'On Battery']
	 **/
	public function getBatteryLevel() {
		$res = $this->send('AT+CBC');

		if(preg_match('/\+CBC:\s*(\d+),(\d+)/', $res, $match)) {
			list(,$status,$level) = $match;
			
			$out = array('Level' => $level);
			switch($status) {
				case 0: $out['Status'] = 'On Battery'; break;
				case 1: $out['Status'] = 'Plugged In'; break;
				case 2: $out['Status'] = 'No Battery'; break;
				default: $out['Status'] = 'Power Fault'; break;
			}
			
			return $out;
		}
	}
	
	/**
	 * Sets the character set for communication
	 *
	 * Currently supported: GSM, ASCII
	 *
	 * @param string Charset to use
	 **/
	public function setCharset($charset) {
		$this->charset = $charset;
		
		if($charset == 'ASCII')
			$charset = 'GSM';
		
		$this->send("AT+CSCS=\"{$charset}\"");
	}
	
	/**
	 * Echos all modem communication in real-time to help debugging
	 **/
	public function enableDebug() {
		$this->debug = true;
	}
	
	/**
	 * Stops echoing debug information
	 **/
	public function disableDebug() {
		$this->debug = false;
	}
	
	/**
	 * @param string Device path, /dev/ttyS# or /dev/ttyUSB# on Linux, COM# on Windows
	 * @param int Pin number for the device SIM card
	 * @param int Connection baud rate
	 **/
	public function __construct($device='/dev/ttyUSB0', $pin=null, $baud=115200) {
		if(!class_exists('phpserial'))
			require(__DIR__ . '/phpserial.php');

		$this->gsm_to_iso = array_merge(
			array(
				"\x00" => '@',
				"\x01" => "\xA3",
				"\x02" => "$",
				"\x03" => "\xA5",
				"\x04" => "\xE8",
				"\x05" => "\xE9",
				"\x06" => "\xF9",
				"\x07" => "\xEC",
				"\x08" => "\xF2",
				"\x09" => "\xC7",
				"\x0A" => "\n",
				"\x0B" => "\xD8",
				"\x0C" => "\xBA",
				"\x0D" => "\r",
				"\x0E" => "\xC5",
				"\x0F" => "\xE5",
			),
			// \x10 - \x1F
			array_combine(range("\x20", "\x3F"), range("\x20", "\x3F")),
			// \x40
			array_combine(range("\x41", "\x5A"), range("\x41", "\x5A")),
			// \x5B - \x60
			array_combine(range("\x61", "\x7A"), range("\x61", "\x7A"))
			// \x7B - \x7F
		);
		
		$this->iso_to_gsm = array_flip($this->gsm_to_iso);
		
		$serial = $this->serial = new phpserial;
		
		$serial->deviceSet($device);
		$serial->confBaudRate($baud);
		$serial->deviceOpen('w+');
		
		$this->send('ATE0');

		$response = $this->send('AT+CPIN?');
		if($response == '+CPIN: SIM PIN') {
			if($pin === null) {
				throw new GSMException('A PIN number is required for this SIM card');
			} else {
				$this->send("AT+CPIN={$pin}", 10);
			}
		}
		
		$this->send('AT+CMGF=1');
		$this->setCharset('GSM');
	}
	
	public function __destruct() {
		$this->serial->deviceClose();
	}

	/**
	 * Sends a message to the modem
	 *
	 * @param string Message to be sent
	 * @param float Delay to wait for response (in seconds)
	 **/
	protected function send($msg, $delay=0.05) {
		if(substr($msg, -1) != "\n") $msg .= "\n";
		
		if($this->debug) echo ">{$msg}";

		if($this->charset == 'GSM')
			$msg = strtr($msg, $this->iso_to_gsm);
		
		$this->serial->sendMessage($msg, $delay);
		$response = $this->serial->readPort();
		
		if($this->charset == 'GSM')
			$response = strtr($response, $this->gsm_to_iso);

		$response = preg_replace('/\n\s*\n/', "\n", $response);

		if($this->debug) {
			$debug = trim(str_replace(array("> \n", "\n"), array('', "\n<"), $response));
			if($debug) echo "<" . $debug . "\n";
		}

		if($response == 'OK') return true;
		if($response == 'ERROR') throw new GSMException($msg . ' returned ' . $response);
		if(substr($response, -2) == 'OK') return trim(substr($response,0,-2));
		
		return $response;
	}
}
class GSMException extends Exception {}
