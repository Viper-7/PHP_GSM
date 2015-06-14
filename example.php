<?php
	include 'gsm.php'; 
	$gsm = new GSM; 
	
	if(isset($_POST['message']) && isset($_POST['number']))
		$gsm->sendSMS($_POST['number'], $_POST['message']);
?><!doctype html>
<html>
	<head>
		<title>GSM Example</title>
		<style type="text/css">
			label span.label {
				vertical-align: top;
				min-width: 80px;
				display: inline-block;
			}
			form .field input, form .field textarea {
				min-width: 280px;
			}
			form .action input {
				width: 360px;
			}
			table.messages .message {
				white-space: pre;
			}
		</style>
	</head>
	<body>
		<div class="container">
			<div class="signal"><?php $signal = $gsm->getSignalInfo(); ?>
				<span class="dbm"><?=$signal['dBm']?> dBm</span>
				<span class="error_rate"><?=$signal['ErrorRate'] ?: 'No'?> Errors</span>
				<span class="rating"><span class="label">Rating:</span><?=$signal['Rating']?></span>
			</div>
			
			<div class="phonebooks"><?php $phonebooks = $gsm->listAllPhonebooks(); ?>
				<?php foreach($phonebooks as $type => $phonebook): if(!$phonebook) continue; ?>
					<div class="phonebook">
						<h2><?=$type?></h2>
						<table class="contacts">
							<thead>
								<tr>
									<th>Name</th>
									<th>Number</th>
								</tr>
							</thead>
							<tfoot></tfoot>
							<tbody>
								<?php foreach($phonebook as $contact): ?>
									<tr>
										<td class="name"><?=$contact['Name']?></td>
										<td class="number"><?=$contact['Number']?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endforeach; ?>
			</div>
			
			<div class="message_stores"><?php $stores = $gsm->listAllSMS(); ?>
				<?php foreach($stores as $type => $messages): if(!$messages) continue;  ?>
					<div class="message_store">
						<h2><?=$type?></h2>
						<table class="messages">
							<thead>
								<tr>
									<th>#</th>
									<th>From</th>
									<th>Time</th>
									<th>Status</th>
									<th>Message</th>
								</tr>
							</thead>
							<tfoot></tfoot>
							<tbody>
								<?php foreach($messages as $message): ?>
									<tr>
										<td><?=is_array($message['Index']) ? implode(',', $message['Index']) : $message['Index']?></td>
										<td><?=$message['From']?></td>
										<td><?=$message['Time']?></td>
										<td><?=$message['Status']?></td>
										<td class="message"><?=$message['Message']?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endforeach; ?>
			</div>
			
			<div class="new_message">
				<form action="" method="POST">
					<div class="field tel">
						<label><span class="label">Number</span><input type="tel" name="number" size="30"></label>
					</div>
					<div class="field textarea">
						<label><span class="label">Message</span><textarea name="message" rows="6" cols="30"></textarea><br>
					</div>
					<div class="field action">
						<input type="submit" value="Send">
					</div>
				</form>
			</div>
		</div>
	</body>
</html>