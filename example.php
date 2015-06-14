<?php
	include 'gsm.php'; 
	$gsm = new GSM; 
	
	if(isset($_POST['delete']) && is_array($_POST['delete']))
		$gsm->deleteSMS(explode(',', key($_POST['delete'])));
		
	if(isset($_POST['message']) && isset($_POST['number']))
		$gsm->sendSMS($_POST['number'], $_POST['message']);
?><!doctype html>
<html>
	<head>
		<title>GSM Example</title>
		<style type="text/css">
			table.messages td.message { white-space: pre; }
			div.sendsms { margin-top: 16px; }
			table.messages { width: 100%; }
			td.message { max-width: 500px; }
			div.new_message { display: none; }
			table.contacts { width: 100%; }
			table.contacts a.sendsms { color: #000; text-decoration: none; }
			div.signal { position: fixed; top: 16px; right: 16px; }
			div.phonebooks {
				width: 270px;
				position: absolute;
				top: 0;
				left: 16px;
				border-right: 1px solid #ccc;
				padding-right: 16px;
				margin-right: 16px;
			}
			div.message_stores {
				min-width: 600px;
				width: 70%;
				position: absolute;
				top: 0;
				left: 316px;
			}
		</style>
	</head>
	<body>
		<div class="container">
			<div class="signal"><?php $signal = $gsm->getSignalInfo(); ?>
				<div class="dbm"><span claass="label">Signal:</span><?=$signal['dBm']?> dBm</div>
				<div class="error_rate"><span class="label">Errors:</span><?=$signal['ErrorRate'] ?: 'No'?></div>
				<div class="rating"><span class="label">Rating:</span><?=$signal['Rating']?></div>
				<div class="sendsms">
					<a href="#" class="sendsms">Send SMS</a>
				</div>
			</div>
			
			<div class="phonebooks"><?php $phonebooks = $gsm->listAllPhonebooks(); ?>
				<h1>Phonebook</h1>
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
									<tr number="<?=htmlentities($contact['Number'], ENT_QUOTES);?>">
										<td class="name"><a href="#" class="sendsms"><?=htmlentities($contact['Name'])?></a></td>
										<td class="number"><a href="#" class="sendsms"><?=htmlentities($contact['Number'])?></a></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="message_stores"><?php $stores = $gsm->listAllSMS(); ?>
				<h1>Messages</h1>
				<?php foreach($stores as $type => $messages): if(!$messages || $type == 'Status Report Storage') continue;  ?>
					<div class="message_store">
						<h2><?=$type?></h2>
						<table class="messages">
							<thead>
								<tr>
									<th>&nbsp;</th>
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
										<td class="actions"><form method="post" action=""><input type="submit" name="delete[<?=$index?>]" value="Delete"></form></td>
										<td><?=$index = is_array($message['Index']) ? implode(',', $message['Index']) : $message['Index']?></td>
										<td><?=htmlentities($message['From'])?></td>
										<td><?=htmlentities($message['Time'])?></td>
										<td><?=htmlentities($message['Status'])?></td>
										<td class="message"><?=htmlentities($message['Message'])?></td>
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
	<link rel="stylesheet" href="//code.jquery.com/ui/1.11.3/themes/smoothness/jquery-ui.css"/>
	<script src="//code.jquery.com/jquery-1.11.3.min.js"></script>
	<script src="//code.jquery.com/ui/1.11.3/jquery-ui.min.js"></script>
	<script type="text/javascript">
		jQuery('a.sendsms').click(function() {
			var tr = jQuery(this).closest('tr');
			if(tr.attr('number')) {
				jQuery('div.tel').find('input').val(tr.attr('number'));
			}
			
			jQuery('div.new_message').dialog({
				modal: true,
				width: 400
			});
			
			jQuery('div.textarea').find('textarea').focus();
		});
	</script>
</html>