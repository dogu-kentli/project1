<?php

class ControllerAccountAccount extends Controller {
	protected function prepareActiveApplicationFeed() {
        
		$feed = array('udef' => array(), 'genel' => array(), 'burs' => array(), 'ozel' => array());
		$store_id = $this->customer->getStoreId();

		$this->load->model('account/basvuru');
		$generalApplications = array_merge(
			$this->model_account_basvuru->getBasvurular($store_id),
			
		);
        
		foreach ($generalApplications as $application) {
			$feed['genel'][$application['basvuru_id']] = array(
				'id' => $application['basvuru_id'],
				'name' => $application['name'],
				'date' => date('d/m/Y', strtotime($application['date_start'])),
				'end_date' => date('d/m/Y', strtotime($application['date_end'])),
				'link' => $this->url->link('account/basvuru', '', true)
			);
		}

		foreach ($this->model_account_basvuru->getBasvurularUdef() as $application) {
			$feed['udef'][$application['basvuru_id']] = array(
				'id' => $application['basvuru_id'],
				'name' => $application['name'],
				'date' => date('d/m/Y', strtotime($application['date_start'])),
				'end_date' => date('d/m/Y', strtotime($application['date_end'])),
				'link' => $this->url->link('account/basvuru_udef', 'bolum=1', true)
			);
		}

		$this->load->model('account/burs_basvuru');
		foreach ($this->model_account_burs_basvuru->getBasvurular($store_id) as $application) {
			$feed['burs'][$application['burs_basvuru_id']] = array(
				'id' => $application['burs_basvuru_id'],
				'name' => $application['name'],
				'date' => date('d/m/Y', strtotime($application['date_start'])),
				'end_date' => date('d/m/Y', strtotime($application['date_end'])),
				'link' => $this->url->link('account/burs_basvuru', '', true)
			);
		}

		$this->load->model('account/anket_formu');
		foreach ($this->model_account_anket_formu->getAnketler() as $application) {
			if ((int)$application['secim'] !== 7) {
				continue;
			}

			$feed['ozel'][$application['eksen_form_id']] = array(
				'id' => $application['eksen_form_id'],
				'name' => $application['name'],
				'date' => date('d/m/Y', strtotime($application['date_added'])),
				'end_date' => '',
				'link' => $this->url->link('account/basvuru_ozel', '', true)
			);
		}

		$this->session->data['active_application_feed'] = $feed;
	}

	public function index() {

		if (!$this->customer->isLogged()) {
			$this->session->data['redirect'] = $this->url->link('account/edit', '', true);

			$this->response->redirect($this->url->link('account/login', '', true));
		}
		
		$this->load->language('account/account');

		$this->document->setTitle($this->language->get('heading_title'));

		if ($this->request->server['HTTPS']) {
			$server = $this->config->get('config_ssl');
		} else {
			$server = $this->config->get('config_url');
		}

		$this->load->model('account/customer');
		$customer_info = $this->model_account_customer->getCustomer($this->customer->getId());
		
		//BAŞVURU TOPLAMLARI
		$this->load->model('account/basvuru');
			
		$store_id = $this->customer->getStoreId();

		$data['total_basvuru'] = $this->model_account_basvuru->getTotalOgrenciBasvuru($this->customer->getId());
		$this->prepareActiveApplicationFeed();
		$data['aktif_basvurular'] = array();
		foreach (array('udef', 'genel', 'burs', 'ozel') as $category) {
			$application = reset($this->session->data['active_application_feed'][$category]);
			if ($application) {
				$application['category'] = $category;
				$application['status'] = 'Başvuruya açık';
				$data['aktif_basvurular'][] = $application;
			}
		}
		$data['aktif_basvurular'] = array_slice($data['aktif_basvurular'], 0, 3);
		
		$this->load->model('account/duyuru');

		$data['total_duyuru'] = $this->model_account_duyuru->getTotalsDuyuru($store_id);
		
		$this->load->model('account/talep');

		$data['total_talep'] = $this->model_account_talep->getTotalTalep();

		$data['grup'] = $this->customer->getGroupId();

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		//ETKİNLİK İSTATİSTİKLERİ
		$this->load->model('account/etkinlik');

		$etkinlikler1 = $this->model_account_etkinlik->getAylikEtkinlikSayilari(date('Y'), '');

		// Tüm ayları sıfırla başlat
		$aylikToplamlar = [];
		for ($i = 1; $i <= 12; $i++) {
			$aylikToplamlar[$i] = 0;
		}

		// Gelen verileri yerleştir
		foreach ($etkinlikler1 as $row) {
			$aylikToplamlar[(int)$row['ay']] = (int)$row['toplam'];
		}

		// JavaScript'e uygun diziye dönüştür
		$data['data_egitim1'] = json_encode(array_values($aylikToplamlar));
		
		$etkinlikler2 = $this->model_account_etkinlik->getAylikEtkinlikSayilari(date('Y', strtotime('-1 year')),'');

		// Tüm ayları sıfırla başlat
		$aylikToplamlar2 = [];
		for ($i = 1; $i <= 12; $i++) {
			$aylikToplamlar2[$i] = 0;
		}

		// Gelen verileri yerleştir
		foreach ($etkinlikler2 as $row) {
			$aylikToplamlar2[(int)$row['ay']] = (int)$row['toplam'];
		}

		// JavaScript'e uygun diziye dönüştür
		$data['data_egitim2'] = json_encode(array_values($aylikToplamlar2));
		$data['map_url'] = HTTP_SERVER.'harita';

		$data['home'] = $this->url->link('account/account', '', true);
		$data['edit'] = $this->url->link('account/edit', '', true);
		$data['basvuru'] = $this->url->link('account/basvuru&bolum=1', '', true);
		$data['basvuru_link'] = $data['basvuru'];
		$data['duyuru'] = $this->url->link('account/duyuru', '', true);
		$data['talep'] = $this->url->link('account/talep', '', true);
		$data['password'] = $this->url->link('account/password', '', true);
		$data['logout'] = $this->url->link('account/logout', '', true);

		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
		
		$this->response->setOutput($this->load->view('account/account', $data));
	}

	public function etkinlikname() {
		$json['error'] = array();
		
		$this->load->model('account/etkinlik');

		if(empty($this->request->get['ay'])) {
			$json['error']['ay'] = "Etkinlik bulunamadı";
		}

		if(isset($this->request->get['ay'])) {
			$ay = $this->request->get['ay'];

			$aylar1 = ["01" => "Ocak", "02" => "Şubat", "03" => "Mart", "04" => "Nisan", "05" => "Mayıs", "06" => "Haziran", "07" => "Temmuz", "08" => "Ağustos", "09" => "Eylül", "10" => "Ekim", "11" => "Kasım", "12" => "Aralık"];

			$aylar2 = array_flip($aylar1);

			$etkinlik_list = $this->model_account_etkinlik->getAylikEtkinlikBasliklari($aylar2[$ay]);

			$html = '';

			$baslik = '<h4>'.$ay.' Ayı Etkinlik Başlıkları</h4>';
			
			foreach($etkinlik_list as $etkinlik) {
				$html .= '<li class="list-group-item">'.$etkinlik['etkinlik_adi'].'</li>';
			}

			$json['html_baslik'] = $baslik;
			
			$json['html'] = $html;

			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
		}
	}
	
	public function universite() {
		$json = array();
		
		$this->load->model('localisation/universite');

		$universite_info = $this->model_localisation_universite->getUniversite($this->request->get['universite_id']);

		if ($universite_info) {
			$this->load->model('localisation/fakulte');

			$json = array(
				'universite_id' => $universite_info['universite_id'],
				'name' => $universite_info['name'],				
				'fakulte' => $this->model_localisation_fakulte->getFakultesByUniversiteId($this->request->get['universite_id'])
			);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
	
	public function fakulte() {
		$json = array();

		$this->load->model('localisation/fakulte');

		$fakulte_info = $this->model_localisation_fakulte->getFakulte($this->request->get['fakulte_id']);

		if ($fakulte_info) {
			$this->load->model('localisation/bolum');

			$json = array(
				'fakulte_id' => $fakulte_info['fakulte_id'],
				'name' => $fakulte_info['name'],
				//'iso_code_2' => $universite_info['iso_code_2'],
				//'iso_code_3' => $universite_info['iso_code_3'],
				//'address_format' => $universite_info['address_format'],
				//'postcode_required' => $universite_info['status'],
				'bolum' => $this->model_localisation_bolum->getBolumesByFakultesId($this->request->get['fakulte_id']),
				'status' => $fakulte_info['status']
			);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
	
	public function sehir() {
		$json = array();
		
		$this->load->model('localisation/sehir');

		$sehir_info = $this->model_localisation_sehir->getSehir($this->request->get['il_id']);

		if ($sehir_info) {
			$this->load->model('localisation/ilce');

			$json = array(
				'il_id' => $sehir_info['il_id'],
				'name' => $sehir_info['name'],				
				'ilce' => $this->model_localisation_ilce->getilceByilId($this->request->get['il_id'])
			);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function bolge() {
		$json = array();
		
		$this->load->model('localisation/country');

		$country_info = $this->model_localisation_country->getCountry($this->request->get['country_id']);

		if ($country_info) {
			$json['bolge_id'] = $country_info['bolge_id'];
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function profile() {
		$json = array(
			'profile_message' => false,
			'kisisel' => false,
			'aile' => false,
			'egitim' => false,
			'akademi' => false,
			'dil' => false,
			'ilgi' => false,
			'status' => false
		);

		$text_profile_message = 'Lütfen profil bilgilerinizde <i class="bi bi-exclamation-circle right-icon text-warning"></i> işaretli alanları kontrol edip eksikleri tamamlayınız.';

		$eksikAlanSayisi = 0;
		$toplamAlanSayisi = 0; // Initialize to 0, we'll count as we go

		$this->load->model('account/customer');
		$ogrenci_info = $this->model_account_customer->getCustomer($this->customer->getId());
		$email_dogrulama = json_decode($ogrenci_info['dogrulama'] ?? '{}', true);

		// custom_field JSON'sa diziye çevir
		if (!empty($ogrenci_info['custom_field']) && is_string($ogrenci_info['custom_field'])) {
			$ogrenci_info['custom_field'] = json_decode($ogrenci_info['custom_field'], true);
		}

		// custom_field JSON'sa diziye çevir
		if (!empty($ogrenci_info['egitim_field']) && is_string($ogrenci_info['egitim_field'])) {
			$ogrenci_info['egitim_field'] = json_decode($ogrenci_info['egitim_field'], true);
		}

		if ($ogrenci_info['status'] && $ogrenci_info['status'] == 3) {
			$json['kisisel'] = true;
			$eksikAlanSayisi++;
			$text_profile_message .= '<span style="display:none;"><br> * Lütfen derneğinizle görüşüp öğrenci durumunuzu <strong>aktif</strong> edin.</span>';
		}

		// KİŞİSEL BİLGİLER
		// Increment toplamAlanSayisi for each field you are checking
		$toplamAlanSayisi += 9; // image
		if (empty($ogrenci_info['image']) || !is_file(DIR_IMAGE . $ogrenci_info['image'])) {
			$json['kisisel'] = true;
			$eksikAlanSayisi++;
			$text_profile_message .= '<span style="display:none;"><br> * Profil resmi bulunamadı.</span>';
		}

		if (empty($email_dogrulama['email']) || $email_dogrulama['email'] === false) {
			$json['kisisel'] = true;
			$eksikAlanSayisi++;
			$text_profile_message .= '<span style="display:none;"><br> * E-posta adresi doğrulanmamış.</span>';
		}

		if (empty($ogrenci_info['kimlikno'])) {
			$json['kisisel'] = true;
			$eksikAlanSayisi++;
			$text_profile_message .= '<span style="display:none;"><br> * T.C Kimlik.</span>';
		}

		if (empty($ogrenci_info['medeni'])) {
			$json['kisisel'] = true;
			$eksikAlanSayisi++;
			$text_profile_message .= '<span style="display:none;"><br> * Medeni haligi</span>';
		}

		if (empty($ogrenci_info['barinma'])) {
			$json['kisisel'] = true;
			$eksikAlanSayisi++;
			$text_profile_message .= '<span style="display:none;"><br> * Barınma Bilgisi</span>';
		}

		if (empty($ogrenci_info['adresi'])) {
			$json['kisisel'] = true;
			$eksikAlanSayisi++;
			$text_profile_message .= '<span style="display:none;"><br> * Adres Bilgisi.</span>';
		}

		if (empty($ogrenci_info['dogum_tarihi']) || $ogrenci_info['dogum_tarihi'] == '0000-00-00') {
			$json['kisisel'] = true;
			$eksikAlanSayisi++;
			$text_profile_message .= '<span style="display:none;"><br> * Doğum Tarihi.</span>';
		}

		if (empty($ogrenci_info['il_id'])) {
			$json['kisisel'] = true;
			$eksikAlanSayisi++;
			$text_profile_message .= '<span style="display:none;"><br> * Yaşadığı şehir.</span>';
		}

		if (empty($ogrenci_info['ilce_id'])) {
			$json['kisisel'] = true;
			$eksikAlanSayisi++;
			$text_profile_message .= '<span style="display:none;"><br> * Yaşadığı İlçe.</span>';
		}

		// AİLE BİLGİLERİ
		$toplamAlanSayisi += 2; // anne_adi, baba_adi
		if (empty($ogrenci_info['anne_adi']) || empty($ogrenci_info['anne_soyadi'])) {
			$json['aile'] = true;
			$eksikAlanSayisi++;
			$text_profile_message .= '<span style="display:none;"><br> * Aile bilgileri / Anne bilgileri.</span>';
		}

		if (empty($ogrenci_info['baba_adi']) || empty($ogrenci_info['baba_soyadi'])) {
			$json['aile'] = true;
			$eksikAlanSayisi++;
			$text_profile_message .= '<span style="display:none;"><br> * Aile bilgileri / Baba bilgileri.</span>';
		}

		// EĞİTİM BİLGİLERİ
		$toplamAlanSayisi += 3; // universite_id, fakulte_id, bolum_id
		if (empty($ogrenci_info['universite_id']) && !isset($ogrenci_info['universite_id']) && empty($ogrenci_info['egitim_field']['yab']['universite'])) {
			$json['egitim'] = true;
			$eksikAlanSayisi++;
			$text_profile_message .= '<span style="display:none;"> <br>* Eğitim Bilgileri / Üniversite.</span>';
		}

		if (empty($ogrenci_info['fakulte_id']) && !isset($ogrenci_info['fakulte_id']) && empty($ogrenci_info['egitim_field']['yab']['fakulte'])) {
			$json['egitim'] = true;
			$eksikAlanSayisi++;
			$text_profile_message .= '<span style="display:none;"><br> * Eğitim Bilgileri / Fakülte.</span>';
		}

		if (empty($ogrenci_info['bolum_id']) && !isset($ogrenci_info['bolum']) && empty($ogrenci_info['egitim_field']['yab']['bolum'])) {
			$json['egitim'] = true;
			$eksikAlanSayisi++;
			$text_profile_message .= '<span style="display:none;"><br> * Eğitim Bilgileri / Bölüm.</span>';
		}

		$toplamAlanSayisi += 3; // egitim_field['i'], egitim_field['o'], egitim_field['l']
		if (!isset($ogrenci_info['egitim_field']['i']) || !is_array($ogrenci_info['egitim_field']['i']) || empty($ogrenci_info['egitim_field']['i'])) {
			$json['egitim'] = true;
			$eksikAlanSayisi++;
			$text_profile_message .= '<span style="display:none;"><br> * Eğitim Bilgileri / İlkokul bilgileri.</span>';
		}

		if (!isset($ogrenci_info['egitim_field']['o']) || !is_array($ogrenci_info['egitim_field']['o']) || empty($ogrenci_info['egitim_field']['o'])) {
			$json['egitim'] = true;
			$eksikAlanSayisi++;
			$text_profile_message .= '<span style="display:none;"><br> * Eğitim Bilgileri / Ortaokul bilgileri.</span>';
		}

		if (!isset($ogrenci_info['egitim_field']['l']) || !is_array($ogrenci_info['egitim_field']['l']) || empty($ogrenci_info['egitim_field']['l'])) {
			$json['egitim'] = true;
			$eksikAlanSayisi++;
			$text_profile_message .= '<span style="display:none;"><br> * Eğitim Bilgileri / Lise bilgileri.</span>';
		}

		// Dil bilgisi (20)
		$toplamAlanSayisi += 1; // custom_field[20]
		if (!isset($ogrenci_info['custom_field'][20]) || !is_array($ogrenci_info['custom_field'][20]) || empty($ogrenci_info['custom_field'][20])) {
			$json['dil'] = true;
			$eksikAlanSayisi++;
			$text_profile_message .= '<span style="display:none;"><br> * Bildiği diller.</span>';
		}

		//AKADEMİK BİLGİLER
		$ogrenci_tezler = $this->model_account_customer->getCustomerTez($this->customer->getId());
		$ogrenci_sertifikalar = $this->model_account_customer->getCustomerSertifika($this->customer->getId());
		$ogrenci_referanslar = $this->model_account_customer->getCustomerReferans($this->customer->getId());
		
		$toplamAlanSayisi += 1; // tezler, sertifikalar, referanslar
		if (empty($ogrenci_tezler) && empty($ogrenci_sertifikalar) && empty($ogrenci_referanslar)) {
			$json['akademik'] = true;
			$eksikAlanSayisi++;
			$text_profile_message .= '<span style="display:none;"><br> * Akademik bilgiler.</span>';
		}
		
		// İlgi alanı (26) ve Hobi (21) – ayrı ayrı kontrol edilmeli
		$ilgiEksik = !isset($ogrenci_info['custom_field'][26]) || !is_array($ogrenci_info['custom_field'][26]) || empty($ogrenci_info['custom_field'][26]);
		$hobiEksik = !isset($ogrenci_info['custom_field'][21]) || !is_array($ogrenci_info['custom_field'][21]) || empty($ogrenci_info['custom_field'][21]);

		$toplamAlanSayisi += 2; // custom_field[26], custom_field[21]
		if ($ilgiEksik) {
			$json['ilgi'] = true;
			$eksikAlanSayisi++;
			$text_profile_message .= '<span style="display:none;"><br> * İlgi alanları bilgileri.</span>';
		}
		
		if ($hobiEksik) {
			$json['ilgi'] = true;
			$eksikAlanSayisi++;
			$text_profile_message .= '<span style="display:none;"><br> * Hobi bilgileri.</span>';
		}
		
		if(!empty($json['kisisel']) || $json['aile'] || $json['egitim'] || $json['akademik'] || $json['dil'] || $json['ilgi']) {
			$json['profile_message'] = $text_profile_message;
		}

		if ($toplamAlanSayisi > 0) {
			$json['profil_yuzdesi'] = intval((($toplamAlanSayisi - $eksikAlanSayisi) / $toplamAlanSayisi) * 100);
		}else{
			$json['profil_yuzdesi'] = 100;
		} 

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

    public function upload() {
		$this->load->language('tool/upload');

		$json = array();

		if (!empty($this->request->files['file']['name']) && is_file($this->request->files['file']['tmp_name'])) {
			// Sanitize the filename
		$filename = basename(html_entity_decode(utf8_strtolower(dosya_temizle($this->request->files['file']['name'])), ENT_QUOTES, 'UTF-8'));

			// Validate the filename length
			if ((utf8_strlen($filename) < 3) || (utf8_strlen($filename) > 64)) {
				$json['error'] = $this->language->get('error_filename');
			}

			// Validate the filename size
			if ($this->request->files['file']['size'] > $this->config->get('config_file_max_size')) {
				$json['error'] = $this->language->get('error_filesize');
			}

			// Allowed file extension types
			$allowed_extensions = preg_split('~\r?\n~', $this->config->get('config_file_ext_allowed'));
			$allowed_extensions = array_map('trim', $allowed_extensions);
			if (!in_array(strtolower(substr(strrchr($filename, '.'), 1)), $allowed_extensions)) {
				$json['error'] = $this->language->get('error_filetype');
			}

			// Allowed file mime types
			$allowed_mimes = preg_split('~\r?\n~', $this->config->get('config_file_mime_allowed'));
			$allowed_mimes = array_map('trim', $allowed_mimes);
			if (!in_array($this->request->files['file']['type'], $allowed_mimes)) {
				$json['error'] = $this->language->get('error_filetype');
			}

			// Check to see if any PHP files are trying to be uploaded
			$content = file_get_contents($this->request->files['file']['tmp_name']);
			if (preg_match('/\<\?php/i', $content)) {
				$json['error'] = $this->language->get('error_filetype');
			}

			// Return any upload error
			if ($this->request->files['file']['error'] != UPLOAD_ERR_OK) {
				$json['error'] = $this->language->get('error_upload_' . $this->request->files['file']['error']);
			}
		} else {
			$json['error'] = $this->language->get('error_upload');
		}

		if (!$json) {
			
			$code = token(32);
			$file = $filename;

			move_uploaded_file($this->request->files['file']['tmp_name'], DIR_DOWNLOAD . $file);

			$this->load->model('tool/upload');
	
			$json['filename'] = $file;
			
			$json['success'] = $this->language->get('text_upload');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}