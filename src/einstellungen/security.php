<?php
namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || exit;

use CLOUDMEISTER\CMX\Buero\PDF\SignatureService;

if (!\function_exists(__NAMESPACE__ . '\\cmx_pdf_signature_admin_url')) {
	function cmx_pdf_signature_admin_url(array $args = []): string {
		$base = \admin_url('admin.php?page=' . \rawurlencode(CMX_SETTINGS_SLUG) . '&tab=system&sub=security');
		return \add_query_arg($args, $base);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_pdf_signature_require_access')) {
	function cmx_pdf_signature_require_access(): void {
		if (!cmx_settings_current_user_can_access()) {
			\wp_die(\esc_html__('Keine Berechtigung.', 'cmx-misbuero'));
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_pdf_signature_format_datetime')) {
	function cmx_pdf_signature_format_datetime(string $value): string {
		if ($value === '') {
			return '-';
		}
		$timestamp = \strtotime($value);
		return $timestamp ? \wp_date('d.m.Y H:i', $timestamp) : $value;
	}
}

\add_action('admin_init', static function (): void {
	\add_settings_section(
		'cmx_sec_pdf_signature',
		\__('PDF-Signatur', 'cmx-misbuero'),
		__NAMESPACE__ . '\\cmx_render_pdf_signature_settings',
		'cmx_tab_system__security'
	);

	\register_setting(
		CMX_SETTINGS_MAIN,
		'ADMIN_PUBLIC_KEYS',
		[
			'type'              => 'array',
			'default'           => [],
			'sanitize_callback' => static function ($value): array {
				if ($value === null) {
					return cmx_get_admin_public_keys();
				}
				return cmx_ssh_public_keys_normalize($value);
			},
		]
	);
	\register_setting(
		CMX_SETTINGS_MAIN,
		'MAXMIND_ACCOUNT_ID',
		[
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => static fn($value): string => (string) \preg_replace('/\D+/', '', (string) $value),
		]
	);
	\register_setting(
		CMX_SETTINGS_MAIN,
		'MAXMIND_LICENSE_KEY',
		[
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => static fn($value): string => \trim(\sanitize_text_field((string) $value)),
		]
	);

	\add_settings_section(
		'cmx_sec_infrastructure',
		\__('Infrastruktur', 'cmx-misbuero'),
		static function (): void {
			$command = 'ssh-keygen -t ed25519 -a 100 -f ~/.ssh/platform.cloudmeister.systems -C "platform.cloudmeister.systems"';
			echo '<p>' . \esc_html__('Schlüssel für die Administration der Infrastruktur.', 'cmx-misbuero') . '</p>';
			echo '<p class="description">' . \esc_html__('Alle hinterlegten Schlüssel erhalten Administrationszugang auf neu bereitgestellten Servern. Ist im Schlüssel ein Kommentar enthalten, wird er automatisch als Name übernommen.', 'cmx-misbuero') . '</p>';
			echo '<p class="cmx-ssh-key-command">';
			echo \esc_html__('SSH Key auf Mac generieren:', 'cmx-misbuero') . ' ';
			echo '<button type="button" id="cmx-copy-ssh-key-command" class="cmx-copy-ssh-key-command" data-command="' . \esc_attr($command) . '" aria-label="' . \esc_attr__('Befehl kopieren', 'cmx-misbuero') . '" title="' . \esc_attr__('Befehl kopieren', 'cmx-misbuero') . '">';
			echo '<svg class="cmx-copy-ssh-key-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>';
			echo '<svg class="cmx-copy-ssh-key-check" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6 9 17l-5-5"></path></svg>';
			echo '</button> ';
			echo '<code>' . \esc_html($command) . '</code>';
			echo ' <span id="cmx-copy-ssh-key-status" class="cmx-copy-ssh-key-status" role="status" aria-live="polite"></span>';
			echo '</p>';
		},
		'cmx_tab_system__security'
	);

	\add_settings_field(
		'cmx_admin_public_keys',
		\__('SSH-Schlüssel', 'cmx-misbuero'),
		static function (): void {
			$keys = cmx_get_admin_public_keys();
			if (!$keys) {
				$keys[] = ['name' => '', 'key' => ''];
			}
			echo '<div id="cmx-admin-public-keys" class="cmx-admin-public-keys"><input type="hidden" name="ADMIN_PUBLIC_KEYS[]" value="">';
			foreach ($keys as $index => $entry) {
				echo '<div class="cmx-admin-public-key" data-cmx-ssh-key-row>';
				echo '<label><span>' . \esc_html__('Name', 'cmx-misbuero') . '</span><input type="text" name="ADMIN_PUBLIC_KEYS[' . \esc_attr((string) $index) . '][name]" value="' . \esc_attr($entry['name']) . '" data-cmx-ssh-key-name autocomplete="off"></label>';
				echo '<label class="cmx-admin-public-key-value"><span>' . \esc_html__('Public Key', 'cmx-misbuero') . '</span><input type="text" name="ADMIN_PUBLIC_KEYS[' . \esc_attr((string) $index) . '][key]" value="' . \esc_attr($entry['key']) . '" class="large-text code" data-cmx-ssh-key-value spellcheck="false" autocapitalize="none" autocomplete="off"></label>';
				echo '<button type="button" class="button button-link-delete cmx-remove-public-key" data-cmx-remove-ssh-key aria-label="' . \esc_attr__('SSH-Schlüssel entfernen', 'cmx-misbuero') . '" title="' . \esc_attr__('SSH-Schlüssel entfernen', 'cmx-misbuero') . '"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>';
				echo '</div>';
			}
			echo '</div>';
			echo '<label for="cmx-admin-public-key-files" id="cmx-admin-public-key-dropzone" class="cmx-admin-public-key-dropzone" role="button" tabindex="0">';
			echo '<span class="dashicons dashicons-upload" aria-hidden="true"></span>';
			echo '<span><strong>' . \esc_html__('Public-Key-Datei hier ablegen', 'cmx-misbuero') . '</strong><small>' . \esc_html__('oder klicken, um eine oder mehrere Dateien auszuwählen', 'cmx-misbuero') . '</small></span>';
			echo '</label>';
			echo '<input type="file" id="cmx-admin-public-key-files" accept=".pub,text/plain,application/octet-stream" multiple hidden>';
			echo '<p id="cmx-admin-public-key-file-status" class="cmx-admin-public-key-file-status" role="status" aria-live="polite"></p>';
			echo '<button type="button" class="button button-secondary" id="cmx-add-public-key">' . \esc_html__('SSH-Schlüssel hinzufügen', 'cmx-misbuero') . '</button>';
			echo '<style>
				.cmx-admin-public-keys{display:grid;gap:10px;margin-bottom:10px}
				.cmx-admin-public-key{display:grid;grid-template-columns:minmax(180px,1fr) minmax(360px,4fr) 40px;align-items:end;gap:10px;padding:12px;border:1px solid #c3c4c7;border-radius:6px;background:#fff}
				.cmx-admin-public-key label{display:grid;gap:5px;font-weight:600}
				.cmx-admin-public-key input{width:100%;height:40px;font-weight:400}
				.cmx-admin-public-key .cmx-remove-public-key{display:inline-flex;align-self:end;align-items:center;justify-content:center;box-sizing:border-box;width:40px;min-width:40px;height:40px;min-height:40px;margin:0;padding:0;border:1px solid #8c8f94;border-radius:4px;background:#fff;color:#d63638;text-decoration:none;box-shadow:0 0 0 transparent;line-height:1}
				.cmx-admin-public-key .cmx-remove-public-key:hover,.cmx-admin-public-key .cmx-remove-public-key:focus{border-color:#8c8f94;background:#f6f7f7;color:#b32d2e}
				.cmx-remove-public-key .dashicons{display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;margin:0;font-size:18px;line-height:1;color:inherit}
				.cmx-admin-public-key-dropzone{display:flex;align-items:center;justify-content:center;gap:12px;box-sizing:border-box;min-height:92px;margin:0 0 8px;padding:16px;border:2px dashed #a7aaad;border-radius:6px;background:#fff;color:#50575e;text-align:center;cursor:pointer;transition:border-color .15s ease,box-shadow .15s ease,background-color .15s ease}
				.cmx-admin-public-key-dropzone:hover,.cmx-admin-public-key-dropzone:focus-within,.cmx-admin-public-key-dropzone.is-dragover{border-color:#2271b1;background:#f0f6fc;box-shadow:0 0 0 1px #2271b1;color:#135e96}
				.cmx-admin-public-key-dropzone .dashicons{width:28px;height:28px;font-size:28px}
				.cmx-admin-public-key-dropzone span:last-child{display:grid;gap:3px}
				.cmx-admin-public-key-dropzone small{font-weight:400;color:#646970}
				.cmx-admin-public-key-file-status{min-height:18px;margin:0 0 8px;color:#646970;font-size:12px}
				.cmx-admin-public-key-file-status:empty{display:none}
				.cmx-admin-public-key-file-status.is-error{color:#b32d2e}
				@media(max-width:782px){.cmx-admin-public-key{grid-template-columns:1fr}.cmx-remove-public-key{justify-self:start}}
				.cmx-ssh-key-command{display:flex;align-items:center;flex-wrap:wrap;gap:6px;margin-top:16px}
				.cmx-copy-ssh-key-command{display:inline-flex;align-items:center;justify-content:center;width:36px;min-width:36px;height:36px;margin:0;padding:0;border:1px solid #cbd5e1;border-radius:6px;background:#fff;color:#1d4ed8;box-shadow:none;cursor:pointer;transition:background .15s ease,border-color .15s ease,color .15s ease,box-shadow .15s ease}
				.cmx-copy-ssh-key-command:hover,.cmx-copy-ssh-key-command:focus{border-color:#2271b1;color:#135e96;background:#f6f9ff;box-shadow:0 0 0 1px rgba(34,113,177,.08);outline:none}
				.cmx-copy-ssh-key-command svg{width:17px;height:17px;stroke:currentColor;stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round}
				.cmx-copy-ssh-key-check{display:none}
				.cmx-copy-ssh-key-command.is-copied{border-color:#16a34a;color:#15803d;background:#f0fdf4}
				.cmx-copy-ssh-key-command.is-copied .cmx-copy-ssh-key-icon{display:none}
				.cmx-copy-ssh-key-command.is-copied .cmx-copy-ssh-key-check{display:block}
				.cmx-copy-ssh-key-command.is-error{border-color:#d63638;color:#b32d2e;background:#fcf0f1}
				.cmx-copy-ssh-key-status{color:#2271b1;font-size:12px}
			</style>';
			echo '<script>
				(function(){
					var list=document.getElementById("cmx-admin-public-keys");
					var addButton=document.getElementById("cmx-add-public-key");
					var nextIndex=list?list.querySelectorAll("[data-cmx-ssh-key-row]").length:0;
					var commentFromKey=function(value){
						var parts=(value||"").trim().split(/\\s+/);
						return parts.length>2?parts.slice(2).join(" "):"";
					};
					var bindRow=function(row){
						var name=row.querySelector("[data-cmx-ssh-key-name]");
						var key=row.querySelector("[data-cmx-ssh-key-value]");
						var remove=row.querySelector("[data-cmx-remove-ssh-key]");
						if(name)name.addEventListener("input",function(){name.dataset.manual=name.value.trim()?"1":"";});
						if(key)key.addEventListener("input",function(){if(name&&name.dataset.manual!=="1")name.value=commentFromKey(key.value);});
						if(remove)remove.addEventListener("click",function(){row.remove();});
					};
					var createRow=function(nameValue,keyValue){
						var row=document.createElement("div");
						row.className="cmx-admin-public-key";row.setAttribute("data-cmx-ssh-key-row","");
						row.innerHTML="<label><span>Name</span><input type=\"text\" name=\"ADMIN_PUBLIC_KEYS["+nextIndex+"][name]\" data-cmx-ssh-key-name autocomplete=\"off\"></label><label class=\"cmx-admin-public-key-value\"><span>Public Key</span><input type=\"text\" name=\"ADMIN_PUBLIC_KEYS["+nextIndex+"][key]\" class=\"large-text code\" data-cmx-ssh-key-value spellcheck=\"false\" autocapitalize=\"none\" autocomplete=\"off\"></label><button type=\"button\" class=\"button button-link-delete cmx-remove-public-key\" data-cmx-remove-ssh-key aria-label=\"SSH-Schlüssel entfernen\" title=\"SSH-Schlüssel entfernen\"><span class=\"dashicons dashicons-trash\" aria-hidden=\"true\"></span></button>";
						nextIndex++;list.appendChild(row);
						row.querySelector("[data-cmx-ssh-key-name]").value=nameValue||"";
						row.querySelector("[data-cmx-ssh-key-value]").value=keyValue||"";
						bindRow(row);
						return row;
					};
					if(list){list.querySelectorAll("[data-cmx-ssh-key-row]").forEach(function(row){var name=row.querySelector("[data-cmx-ssh-key-name]");var key=row.querySelector("[data-cmx-ssh-key-value]");if(name&&name.value.trim()!==commentFromKey(key?key.value:""))name.dataset.manual="1";bindRow(row);});}
					if(addButton&&list)addButton.addEventListener("click",function(){
						createRow("","").querySelector("[data-cmx-ssh-key-name]").focus();
					});
					var dropzone=document.getElementById("cmx-admin-public-key-dropzone");
					var fileInput=document.getElementById("cmx-admin-public-key-files");
					var fileStatus=document.getElementById("cmx-admin-public-key-file-status");
					var isPublicKey=function(value){return /^(?:ssh-(?:rsa|dss|ed25519)|ecdsa-sha2-[^\\s]+|sk-(?:ssh-ed25519|ecdsa-sha2-[^\\s]+)@openssh\\.com|[^\\s]+-cert-v01@openssh\\.com)\\s+\\S+(?:\\s+.*)?$/.test(value);};
					var fileNameWithoutExtension=function(fileName){return String(fileName||"").replace(/\\.pub$/i,"").trim();};
					var placeImportedKey=function(key,fileName){
						var row=Array.prototype.find.call(list.querySelectorAll("[data-cmx-ssh-key-row]"),function(candidate){var name=candidate.querySelector("[data-cmx-ssh-key-name]");var value=candidate.querySelector("[data-cmx-ssh-key-value]");return name&&value&&!name.value.trim()&&!value.value.trim();});
						var name=commentFromKey(key)||fileNameWithoutExtension(fileName);
						if(!row)row=createRow(name,key);
						else{row.querySelector("[data-cmx-ssh-key-name]").value=name;row.querySelector("[data-cmx-ssh-key-value]").value=key;}
					};
					var importFiles=function(files){
						var selected=Array.prototype.slice.call(files||[]);
						if(!selected.length)return;
						Promise.all(selected.map(function(file){
							if(file.size>1048576)return Promise.resolve({file:file,error:"Datei ist zu groß."});
							return file.text().then(function(content){return {file:file,key:String(content||"").replace(/\\r\\n?/g,"\\n").trim()};}).catch(function(){return {file:file,error:"Datei konnte nicht gelesen werden."};});
						})).then(function(items){
							var imported=0;var errors=[];
							items.forEach(function(item){
								if(item.error){errors.push(item.file.name+": "+item.error);return;}
								if(!isPublicKey(item.key)){errors.push(item.file.name+": Kein gültiger Public Key erkannt.");return;}
								placeImportedKey(item.key,item.file.name);imported++;
							});
							var message=imported?(imported===1?"Public Key wurde hinzugefügt.":imported+" Public Keys wurden hinzugefügt."):"";
							if(errors.length)message=(message?message+" ":"")+errors.join(" ");
							if(fileStatus){fileStatus.textContent=message;fileStatus.classList.toggle("is-error",errors.length>0);}
						});
					};
					if(dropzone&&fileInput&&list){
						["dragenter","dragover"].forEach(function(name){dropzone.addEventListener(name,function(event){event.preventDefault();event.dataTransfer.dropEffect="copy";dropzone.classList.add("is-dragover");});});
						["dragleave","drop"].forEach(function(name){dropzone.addEventListener(name,function(){dropzone.classList.remove("is-dragover");});});
						dropzone.addEventListener("drop",function(event){event.preventDefault();importFiles(event.dataTransfer.files);});
						dropzone.addEventListener("keydown",function(event){if(event.key==="Enter"||event.key===" "){event.preventDefault();fileInput.click();}});
						fileInput.addEventListener("change",function(){importFiles(fileInput.files);fileInput.value="";});
					}
					var button=document.getElementById("cmx-copy-ssh-key-command");
					var status=document.getElementById("cmx-copy-ssh-key-status");
					if(!button)return;
					var command=button.getAttribute("data-command")||"";
					var fallbackCopy=function(){
						var field=document.createElement("textarea");
						field.value=command;
						field.style.position="fixed";
						field.style.opacity="0";
						document.body.appendChild(field);
						field.select();
						var copied=false;
						try{copied=document.execCommand("copy");}catch(error){}
						document.body.removeChild(field);
						return copied;
					};
					var showResult=function(copied){
						var message=copied?"Befehl kopiert.":"Befehl konnte nicht kopiert werden.";
						if(status)status.textContent=message;
						button.title=message;
						button.classList.toggle("is-copied",copied);
						button.classList.toggle("is-error",!copied);
						setTimeout(function(){
							if(status)status.textContent="";
							button.title="Befehl kopieren";
							button.classList.remove("is-copied","is-error");
						},1800);
					};
					button.addEventListener("click",function(){
						if(navigator.clipboard&&typeof navigator.clipboard.writeText==="function"&&window.isSecureContext){
							navigator.clipboard.writeText(command).then(function(){showResult(true);}).catch(function(){showResult(fallbackCopy());});
							return;
						}
						showResult(fallbackCopy());
					});
				}());
			</script>';
		},
		'cmx_tab_system__security',
		'cmx_sec_infrastructure'
	);

	\add_settings_section(
		'cmx_sec_maxmind',
		\__('MaxMind', 'cmx-misbuero'),
		static function (): void {
			echo '<p>' . \esc_html__('Zugangsdaten für MaxMind GeoIP-Dienste.', 'cmx-misbuero') . '</p>';
			echo '<p class="description">' . \esc_html__('Die Werte werden beim Ersetzen der cloud-Ini-Platzhalter verwendet.', 'cmx-misbuero') . '</p>';
		},
		'cmx_tab_system__security'
	);

	\add_settings_field(
		'cmx_maxmind_credentials',
		\__('Zugangsdaten', 'cmx-misbuero'),
		static function (): void {
			$icon_show_url = \defined('CMX_PLUGIN_URL') ? (string) \constant('CMX_PLUGIN_URL') . 'assets/see.png' : '';
			$icon_hide_url = \defined('CMX_PLUGIN_URL') ? (string) \constant('CMX_PLUGIN_URL') . 'assets/hide.png' : '';
			echo '<div class="cmx-maxmind-fields-row">';
			echo '<div class="cmx-maxmind-field">';
			echo '<label for="cmx-maxmind-account-id">' . \esc_html__('Account ID', 'cmx-misbuero') . '</label>';
			echo '<span class="cmx-maxmind-config-field cmx-maxmind-account-id-wrap" data-cmx-maxmind-config-drop>';
			echo '<input type="text" id="cmx-maxmind-account-id" name="MAXMIND_ACCOUNT_ID" value="' . \esc_attr((string) \get_option('MAXMIND_ACCOUNT_ID', '')) . '" class="regular-text code" inputmode="numeric" pattern="[0-9]*" autocomplete="off">';
			echo '</span>';
			echo '<p class="description"><code>{{ MAXMIND_ACCOUNT_ID }}</code></p>';
			echo '</div>';
			echo '<div class="cmx-maxmind-field">';
			echo '<label for="cmx-maxmind-license-key">' . \esc_html__('License Key', 'cmx-misbuero') . '</label>';
			echo '<span class="cmx-maxmind-config-field cmx-maxmind-license-key-wrap" data-cmx-maxmind-config-drop>';
			echo '<input type="password" id="cmx-maxmind-license-key" name="MAXMIND_LICENSE_KEY" value="' . \esc_attr((string) \get_option('MAXMIND_LICENSE_KEY', '')) . '" class="regular-text code" autocomplete="new-password" spellcheck="false" autocapitalize="none">';
			echo '<button type="button" id="cmx-maxmind-license-key-toggle" class="button-link cmx-maxmind-license-key-toggle" aria-label="' . \esc_attr__('License Key anzeigen', 'cmx-misbuero') . '" aria-pressed="false" title="' . \esc_attr__('License Key anzeigen', 'cmx-misbuero') . '">';
			echo '<span class="cmx-maxmind-license-key-icon is-show" aria-hidden="true"></span>';
			echo '</button></span>';
			echo '<p class="description"><code>{{ MAXMIND_LICENSE_KEY }}</code></p>';
			echo '</div></div>';
			echo '<p class="description">' . \esc_html__('GeoIP.conf auf eines der beiden Felder ziehen, um beide Werte einzulesen.', 'cmx-misbuero') . '</p>';
			echo '<p class="cmx-maxmind-config-status" data-cmx-maxmind-config-status role="status" aria-live="polite"></p>';
			echo '<style>
				.cmx-maxmind-fields-row{display:grid;grid-template-columns:minmax(220px,1fr) minmax(360px,2fr);align-items:start;gap:16px;width:100%;max-width:75em}
				.cmx-maxmind-field{display:grid;gap:5px;min-width:0}
				.cmx-maxmind-field>label{font-weight:600}
				.cmx-maxmind-config-field{position:relative;display:flex;max-width:100%;border-radius:5px;transition:box-shadow .15s ease,background-color .15s ease}
				.cmx-maxmind-account-id-wrap,.cmx-maxmind-license-key-wrap{width:100%}
				.cmx-maxmind-config-field.is-dragover{background:#f0f6fc;box-shadow:0 0 0 2px #2271b1}
				.cmx-maxmind-account-id-wrap #cmx-maxmind-account-id,
				.cmx-maxmind-license-key-wrap #cmx-maxmind-license-key{box-sizing:border-box;width:100%!important;max-width:none;height:40px;padding-right:62px}
				.cmx-maxmind-account-id-wrap #cmx-maxmind-account-id{padding-right:8px}
				.cmx-maxmind-license-key-toggle{position:absolute;top:50%;right:2px;display:inline-flex;align-items:center;justify-content:center;width:52px;height:36px;margin:0;padding:0;border:0!important;background:transparent!important;box-shadow:none!important;transform:translateY(-50%);text-decoration:none!important}
				.cmx-maxmind-license-key-toggle:hover,.cmx-maxmind-license-key-toggle:focus,.cmx-maxmind-license-key-toggle:active{border:0!important;background:#f0f6fc!important;box-shadow:none!important;outline:none}
				.cmx-maxmind-license-key-toggle:focus-visible{box-shadow:0 0 0 2px #2271b1!important}
				.cmx-maxmind-license-key-icon{display:block;width:48px;height:32px;background-repeat:no-repeat;background-position:center;background-size:contain}
				.cmx-maxmind-license-key-icon.is-show{background-image:url("' . \esc_url($icon_show_url) . '")}
				.cmx-maxmind-license-key-icon.is-hide{background-image:url("' . \esc_url($icon_hide_url) . '")}
				.cmx-maxmind-config-status{margin:4px 0 0;color:#2271b1;font-size:12px}
				.cmx-maxmind-config-status:empty{display:none}
				.cmx-maxmind-config-status.is-error{color:#b32d2e}
				@media(max-width:782px){.cmx-maxmind-fields-row{grid-template-columns:1fr}}
			</style>';
			echo '<script>
				(function(){
					var input=document.getElementById("cmx-maxmind-license-key");
					var accountId=document.getElementById("cmx-maxmind-account-id");
					var toggle=document.getElementById("cmx-maxmind-license-key-toggle");
					if(!input||!accountId||!toggle)return;
					var icon=toggle.querySelector(".cmx-maxmind-license-key-icon");
					var sync=function(){
						var visible=input.type==="text";
						var label=visible?"License Key ausblenden":"License Key anzeigen";
						toggle.setAttribute("aria-label",label);
						toggle.setAttribute("title",label);
						toggle.setAttribute("aria-pressed",visible?"true":"false");
						if(icon)icon.className="cmx-maxmind-license-key-icon "+(visible?"is-hide":"is-show");
					};
					toggle.addEventListener("click",function(){input.type=input.type==="password"?"text":"password";sync();input.focus();});
					sync();
					var statuses=Array.prototype.slice.call(document.querySelectorAll("[data-cmx-maxmind-config-status]"));
					var showImportStatus=function(message,isError){statuses.forEach(function(status){status.textContent=message;status.classList.toggle("is-error",!!isError);});};
					var configValue=function(content,name){
						var pattern=new RegExp("^\\\\s*"+name+"\\\\s*(?:=|\\\\s)\\\\s*[\"\']?([^#\\\\s\"\']+)","im");
						var match=String(content||"").replace(/^\\uFEFF/,"").match(pattern);
						return match?String(match[1]||"").trim():"";
					};
					var importConfig=function(files){
						var file=files&&files[0]?files[0]:null;
						if(!file)return;
						if(file.size>1048576){showImportStatus("Datei ist zu groß.",true);return;}
						file.text().then(function(content){
							var importedAccountId=configValue(content,"AccountID");
							var importedLicenseKey=configValue(content,"LicenseKey");
							if(!/^\\d+$/.test(importedAccountId)||!importedLicenseKey){showImportStatus("Keine gültige GeoIP.conf mit AccountID und LicenseKey erkannt.",true);return;}
							accountId.value=importedAccountId;input.value=importedLicenseKey;
							accountId.dispatchEvent(new Event("input",{bubbles:true}));input.dispatchEvent(new Event("input",{bubbles:true}));
							showImportStatus("GeoIP.conf wurde eingelesen. Bitte Änderungen speichern.",false);
						}).catch(function(){showImportStatus("GeoIP.conf konnte nicht gelesen werden.",true);});
					};
					document.querySelectorAll("[data-cmx-maxmind-config-drop]").forEach(function(field){
						["dragenter","dragover"].forEach(function(name){field.addEventListener(name,function(event){event.preventDefault();if(event.dataTransfer)event.dataTransfer.dropEffect="copy";field.classList.add("is-dragover");});});
						["dragleave","drop"].forEach(function(name){field.addEventListener(name,function(){field.classList.remove("is-dragover");});});
						field.addEventListener("drop",function(event){event.preventDefault();importConfig(event.dataTransfer?event.dataTransfer.files:null);});
					});
				}());
			</script>';
		},
		'cmx_tab_system__security',
		'cmx_sec_maxmind'
	);
});

function cmx_render_pdf_signature_settings(): void {
	$manager = SignatureService::instance()->certificateManager();
	$info = $manager->info();
	$status = !empty($info['exists'])
		? \__('Aktiv: PDFs werden unsichtbar digital signiert.', 'cmx-misbuero')
		: \__('Fehlt: PDFs können noch nicht signiert werden.', 'cmx-misbuero');
	$generate_url = \wp_nonce_url(
		\admin_url('admin-post.php?action=cmx_pdf_signature_generate'),
		'cmx_pdf_signature_generate'
	);
	$renew_url = \wp_nonce_url(
		\admin_url('admin-post.php?action=cmx_pdf_signature_renew'),
		'cmx_pdf_signature_renew'
	);
	$download_url = \wp_nonce_url(
		\admin_url('admin-post.php?action=cmx_pdf_signature_download_public'),
		'cmx_pdf_signature_download_public'
	);

	$message = isset($_GET['cmx_pdf_signature']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_pdf_signature'])) : '';
	if ($message === 'generated') {
		echo '<div class="notice notice-success inline"><p>' . \esc_html__('Zertifikat wurde erzeugt.', 'cmx-misbuero') . '</p></div>';
	} elseif ($message === 'renewed') {
		echo '<div class="notice notice-success inline"><p>' . \esc_html__('Zertifikat wurde erneuert.', 'cmx-misbuero') . '</p></div>';
	} elseif ($message === 'error') {
		echo '<div class="notice notice-error inline"><p>' . \esc_html__('Zertifikatsaktion fehlgeschlagen. Details stehen im PHP-Error-Log.', 'cmx-misbuero') . '</p></div>';
	}

	echo '<p>' . \esc_html__('Mis Büro signiert erzeugte PDFs automatisch und unsichtbar. Das Layout bleibt unverändert; der private Schlüssel wird nie ausgegeben.', 'cmx-misbuero') . '</p>';
	echo '<table class="widefat striped" style="max-width:920px"><tbody>';
	echo '<tr><th scope="row">' . \esc_html__('Status', 'cmx-misbuero') . '</th><td>' . \esc_html($status) . '</td></tr>';
	echo '<tr><th scope="row">' . \esc_html__('Zertifikat vorhanden', 'cmx-misbuero') . '</th><td>' . (!empty($info['exists']) ? \esc_html__('Ja', 'cmx-misbuero') : \esc_html__('Nein', 'cmx-misbuero')) . '</td></tr>';
	echo '<tr><th scope="row">' . \esc_html__('Erstellt am', 'cmx-misbuero') . '</th><td>' . \esc_html(cmx_pdf_signature_format_datetime((string) ($info['created_at'] ?? ''))) . '</td></tr>';
	echo '<tr><th scope="row">' . \esc_html__('Gültig ab', 'cmx-misbuero') . '</th><td>' . \esc_html(cmx_pdf_signature_format_datetime((string) ($info['valid_from'] ?? ''))) . '</td></tr>';
	echo '<tr><th scope="row">' . \esc_html__('Ablaufdatum', 'cmx-misbuero') . '</th><td>' . \esc_html(cmx_pdf_signature_format_datetime((string) ($info['expires_at'] ?? ''))) . '</td></tr>';
	echo '<tr><th scope="row">' . \esc_html__('Fingerprint SHA-256', 'cmx-misbuero') . '</th><td><code>' . \esc_html((string) ($info['fingerprint'] ?? '-')) . '</code></td></tr>';
	echo '<tr><th scope="row">' . \esc_html__('Speicherort', 'cmx-misbuero') . '</th><td><code>' . \esc_html((string) ($info['directory'] ?? '')) . '</code></td></tr>';
	echo '</tbody></table>';
	echo '<p class="submit">';
	echo '<a class="button button-secondary" href="' . \esc_url($generate_url) . '">' . \esc_html__('Zertifikat erzeugen', 'cmx-misbuero') . '</a> ';
	echo '<a class="button button-secondary" href="' . \esc_url($renew_url) . '">' . \esc_html__('Zertifikat erneuern', 'cmx-misbuero') . '</a> ';
	if (!empty($info['exists'])) {
		echo '<a class="button" href="' . \esc_url($download_url) . '">' . \esc_html__('Public Certificate herunterladen', 'cmx-misbuero') . '</a>';
	}
	echo '</p>';
}

\add_action('admin_post_cmx_pdf_signature_generate', static function (): void {
	cmx_pdf_signature_require_access();
	\check_admin_referer('cmx_pdf_signature_generate');
	$result = SignatureService::instance()->certificateManager()->generate(false);
	if (\is_wp_error($result)) {
		SignatureService::log('Zertifikat erzeugen fehlgeschlagen.', ['error' => $result->get_error_message()]);
		\wp_safe_redirect(cmx_pdf_signature_admin_url(['cmx_pdf_signature' => 'error']));
		exit;
	}
	\wp_safe_redirect(cmx_pdf_signature_admin_url(['cmx_pdf_signature' => 'generated']));
	exit;
});

\add_action('admin_post_cmx_pdf_signature_renew', static function (): void {
	cmx_pdf_signature_require_access();
	\check_admin_referer('cmx_pdf_signature_renew');
	$result = SignatureService::instance()->certificateManager()->renew();
	if (\is_wp_error($result)) {
		SignatureService::log('Zertifikat erneuern fehlgeschlagen.', ['error' => $result->get_error_message()]);
		\wp_safe_redirect(cmx_pdf_signature_admin_url(['cmx_pdf_signature' => 'error']));
		exit;
	}
	\wp_safe_redirect(cmx_pdf_signature_admin_url(['cmx_pdf_signature' => 'renewed']));
	exit;
});

\add_action('admin_post_cmx_pdf_signature_download_public', static function (): void {
	cmx_pdf_signature_require_access();
	\check_admin_referer('cmx_pdf_signature_download_public');
	$certificate = SignatureService::instance()->certificateManager()->publicCertificate();
	if ($certificate === '') {
		\wp_die(\esc_html__('Public Certificate ist nicht vorhanden.', 'cmx-misbuero'));
	}
	\nocache_headers();
	\header('Content-Type: application/x-pem-file; charset=utf-8');
	\header('Content-Disposition: attachment; filename="misbuero-public-certificate.crt"');
	echo $certificate;
	exit;
});
