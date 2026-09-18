<?php
class ControllerAccountBursBasvuru extends Controller {
    private $error=array();
    
    public function index() {
        $this->load->model('account/burs_basvuru');
        
        if (isset($this->request->get['code'])) {
            $this->session->data['basvuru_code'] = $this->request->get['code'];
        }   
           
        if (!$this->customer->isLogged()) {
            $this->session->data['redirect']=$this->url->link('account/burs_basvuru', '', true);

            $this->response->redirect($this->url->link('account/login', '', true));
        }

        if(!empty($this->session->data['basvuru_code'])) {
             $code = $this->session->data['basvuru_code'];
            
             $this->load->model('account/burs_basvuru');
             $bolum = 'burs';

             $get_code = $this->share($code);
             
             if(empty($get_code)) {
               $this->model_account_burs_basvuru->addbasvuruSHare($code,$bolum);

               unset($this->session->data['basvuru_code']);
             }
        }
            
        $this->getList();
    }
    
    public function edit() {
        if ( !$this->customer->isLogged()) {
            $this->session->data['redirect']=$this->url->link('account/burs_basvuru', '', true);
            $this->response->redirect($this->url->link('account/login', '', true));
        }
        
        $this->load->model('account/customer');
        
        if (($this->request->server['REQUEST_METHOD']=='POST')) {
            $this->session->data['success']=$this->language->get('text_edit');
            $this->response->redirect($this->url->link('account/account', '', true));
        }
        
        $this->getForm();
    }
    
    public function getList() {
        $this->load->language('account/basvuru');

        $this->load->model('account/customer');
        $this->load->model('account/burs_basvuru');
        $this->document->setTitle("Burs Başvuruları");
        unset($this->session->data['store_id3']);
        
        if (isset($this->session->data['success'])) {
            $data['success']=$this->session->data['success'];
            unset($this->session->data['success']);
       }else{
            $data['success'] = '';
        }
        
        $data['breadcrumbs']=array();

        $this->load->model('tool/image');
        $this->load->model('design/banner');
        $this->load->model('setting/store');
        $bugun=date('Y-m-d');

        $store_id=$this->customer->getStoreId();
        //DERNEKLERİN BAŞVURULARI
        $results=$this->model_account_burs_basvuru->getBasvurular($store_id);
        
        foreach($results as $result) {
            $dernek = "";
            
            if($result['store_id'] == 0) {
                $dernek="UDEF";
            }
            
            // $basvuru_status = $this->model_account_burs_basvuru->getCustomerBasvuru($result['burs_basvuru_id']);
            $sure=floor(((strtotime($result['date_end'])) - (strtotime($bugun))) / (60 * 60 * 24));

            if($result['image'] && is_file(DIR_IMAGE . $result['image'])) {
                $image_size=getimagesize(DIR_IMAGE . $result['image']);
                $x=$image_size[0] / 1.5;
                $y=$image_size[1] / 1.5;
                $image=$this->model_tool_image->resize($result['image'], 1000, $y);
                $thumb=$this->model_tool_image->resize($result['image'], $x, $y);
            } else {
                $image=$this->model_tool_image->resize('placeholder_banner.png', 100, 100);
                $thumb='';
            }
            
            if($result['kontenjan'] == 0) {
                $kontenjan = 1000;
            } else {
                $kontenjan=$result['kontenjan'];
            }
            
            $store_info['name'] = "UDEF";
            
            if($result['store_id'] !=0) {
                $store_info = $this->model_setting_store->getStore($result['store_id']);
            }
            
            $status_array=[ 
                '0'  =>'Başvuru alınmıştır',
                '2'  =>'Değerlendirme Sürecinde',
                '1'  =>'Burs İçin Uygun Görüldü',
                '3'  =>'Revize talep edildi',
                '4'  =>'Uygun Görülmedi',
                '5'  =>'Başvuru Tamamlandı',
            ];

            $ogrenci_basvuru_info=$this->model_account_burs_basvuru->getBasvuruOgrenci($result['burs_basvuru_id']);
            $total_basvuru=$this->model_account_burs_basvuru->getToplamBasvuru($result['burs_basvuru_id']);
            
            if($ogrenci_basvuru_info) {
                $basvuru_status=$ogrenci_basvuru_info['status'];
                $basvuru=true;
            }else {
                $basvuru_status='';
                $last_status='';
                $basvuru=false;
                $last_status = '';
            }

         
            if(!empty($result['code'])) {
                $code = $this->share($result['code']);

                if($code != $result['code']) {  
                 continue;
                }
            }

            if($result['secim'] == 3) {
                $ogrenci_info = $this->model_account_customer->getProjeliOgrenciler($this->customer->getId());

                if(empty($ogrenci_info)) {  
                  continue;
                }
            }

            if ($result['secim'] == 2) {
                $stores = json_decode($result['stores_array'], true); // ["75"] gibi array olacak
                
                if (!in_array($this->customer->getStoreId(), $stores)) {
                    continue;
                }
            }

            
            $data['basvurular'][]=array(
                'burs_basvuru_id'=> $result['burs_basvuru_id'],
                'name'=> $result['name'],
                'basvuru'=> $basvuru,
                'store'=> $dernek,
                'kontenjan'=> $kontenjan,
                'total'=> $total_basvuru,
                'image'=> $image,
                'thumb'=> $thumb,
                'description'=> utf8_substr(trim(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8'))), 0, 15)."...",
                'date_start'=> date('d/m/Y', strtotime($result['date_start'])),
                'date_end'=> date('d/m/Y', strtotime($result['date_end'])),
                'status' => @$status_array[$basvuru_status],
                'sure' => $sure,
                'edit' => $this->url->link('account/burs_basvuru/edit', 'burs_basvuru_id='. $result['burs_basvuru_id'], true),
            );
        }

        $activeApplicationFeed = $this->session->data['active_application_feed'] ?? array('genel' => array(), 'burs' => array(), 'ozel' => array());
        $activeApplicationFeed['burs'] = array();
        foreach ($data['basvurular'] ?? array() as $application) {
            $activeApplicationFeed['burs'][$application['burs_basvuru_id']] = array(
                'id' => $application['burs_basvuru_id'],
                'name' => $application['name'],
                'date' => $application['date_start'],
                'end_date' => $application['date_end'],
                'link' => $this->url->link('account/burs_basvuru', '', true)
            );
        }
        $this->session->data['active_application_feed'] = $activeApplicationFeed;
        
        $data['back']=$this->url->link('account/account', '', true);
        $data['edit']=$this->url->link('account/account/edit', '', true);
        $data['footer']=$this->load->controller('common/footer');
        $data['header']=$this->load->controller('common/header');
        $data['basvuru_ajax']=false;
        
        if(isset($this->request->get['bolum'])) {
            $data['basvuru_ajax']=true;
        }
        
        $this->response->setOutput($this->load->view('account/basvuru_burs', $data));
    }
    
    public function getForm() {
        $this->load->model('account/customer');
        $this->load->model('account/custom_field');
        $this->load->model('account/burs_basvuru');

        $ogrenci_info=$this->model_account_customer->getCustomer($this->customer->getId());
        $custom_field_info=isset($ogrenci_info['custom_field']) ? json_decode($ogrenci_info['custom_field'], true): [];
        
        if ( !is_array($custom_field_info)) {
            $custom_field_info=[]; // Varsayılan boş dizi
        }
        
        $custom_field_dil=( !empty($custom_field_info[20]) && is_array($custom_field_info[20])) ? implode(',', array_map('intval', $custom_field_info[20])) : ''; // Varsayılan boş değer
        
        if ( !empty($custom_field_dil)) {
            $diller_array=$this->model_account_custom_field->getCustomFieldName($custom_field_dil);
        }else{
            $diller_array=[];
        }
        
        $custom_field_hobi=( !empty($custom_field_info[26]) && is_array($custom_field_info[26])) ? implode(',', array_map('intval', $custom_field_info[26])) : ''; // Varsayılan boş değer
        
       if ( !empty($custom_field_dil)) {
            $hobiler_array=$this->model_account_custom_field->getCustomFieldName($custom_field_hobi);
       }else{
            $hobiler_array=[];
        }
        
        //EĞİTİM BİLGİLERİ
        if ( !empty($ogrenci_info['egitim_field'])) {
            $egitim_bilgileri=json_decode($ogrenci_info['egitim_field'], true);
        }else{
            $egitim_bilgileri=array();
        }
        
        $sinif_id=1;
        
        if (!empty($egitim_bilgileri['d']['universite'])) {
            $key='d';
        }elseif( !empty($egitim_bilgileri['yl']['universite'])) {
            $key='yl';
        } elseif(!empty($egitim_bilgileri['ls']['universite'])) {
            $key='ls';
        }else{
            $key='';
        }

        if(!empty($egitim_bilgileri['yab']['universite'])) {
            $key = 'yab';
        }

        if(!empty($egitim_bilgileri['l']['okul'])) {
            $lise = $egitim_bilgileri['l']['okul'];
        }
        
        if (!empty($egitim_bilgileri[$key]['universite'])) {
            $universite=$egitim_bilgileri[$key]['universite'];
            $universite_id=$egitim_bilgileri[$key]['u_id'];
            $sinif_id=@$egitim_bilgileri[$key]['sinif'];
        }
        
        if ( !empty($egitim_bilgileri[$key]['fakulte'])) {
            $fakulte=$egitim_bilgileri[$key]['fakulte'];
            $fakulte_id=$egitim_bilgileri[$key]['f_id'];
        }
        
        if ( !empty($egitim_bilgileri[$key]['bolum'])) {
            $bolum=$egitim_bilgileri[$key]['bolum'];
            $bolum_id=$egitim_bilgileri[$key]['b_id'];
        }

       
        $sinif_info=$this->model_account_custom_field->getCustomFieldSinif($sinif_id);

        $bildigi_diller=implode(',', $diller_array);
        $hobiler=implode(',', $hobiler_array);
        $form='add';
        $egitim_bilgileri=[];
        $aile_bilgileri=[];
        $sosyal_faaliyetler=[];
        $ozel_durumlar=[];
        $dosyalar=[];
        $bank_account=[];
        
        if(isset($this->request->get['burs_basvuru_id'])) {
            $ogrenci_basvuru_info=$this->model_account_burs_basvuru->getBasvuruOgrenci($this->request->get['burs_basvuru_id']);
            
            if($ogrenci_basvuru_info) {
                $burs_basvuru_ogrenci_id=$ogrenci_basvuru_info['burs_basvuru_ogrenci_id'];
                $egitim_bilgileri=json_decode($ogrenci_basvuru_info['egitim_bilgileri'], true);
                $aile_bilgileri=json_decode($ogrenci_basvuru_info['aile_bilgileri'], true);
                $sosyal_faaliyetler=json_decode($ogrenci_basvuru_info['sosyal_faaliyetler'], true);
                $ozel_durumlar=json_decode($ogrenci_basvuru_info['ozel_durumlar'], true);
                $bank_account=json_decode($ogrenci_basvuru_info['bank_account'], true);
                $dosyalar= !empty($ogrenci_basvuru_info['dosyalar']) ? json_decode($ogrenci_basvuru_info['dosyalar'], true): [];
                $form='edit';
            }
        }
        
        $data=[ 'burs_basvuru_ogrenci_id' => isset($burs_basvuru_ogrenci_id) ? $burs_basvuru_ogrenci_id : '',
        'form'=>$form,
        'name'=>$ogrenci_info['firstname'] ." ". $ogrenci_info['lastname'],
        'gender'=>$ogrenci_info['gender'] ?? '',
        'telephone'=>$ogrenci_info['telephone'] ?? '',
        'email'=>$ogrenci_info['email'] ?? '',
        'kimlikno'=>$ogrenci_info['kimlikno'] ?? '',
        'medeni'=>$ogrenci_info['medeni'] ?? '',
        'address'=>$ogrenci_info['adresi'] ?? '',
        'dogum_yeri'=>$ogrenci_basvuru_info['dogum_yeri'] ?? '',
        'dogum_tarihi'=>$ogrenci_info['dogum_tarihi'] ?? '',
        'universite'=>$universite ?? '',
        'universite_id'=>$universite_id ?? '',
        'lise' => !empty($lise) ? $lise : ($egitim_bilgileri['lise'] ?? ''),
        'fakulte'=>$fakulte ?? '',
        'fakulte_id'=>$fakulte_id ?? '',
        'bolum'=>$bolum ?? '',
        'bolum_id'=>$bolum_id ?? '',
        'sinif'=>$egitim_bilgileri['sinif'] ?? @$sinif_info['name'],
        'kaldigi_yer'=>$ogrenci_basvuru_info['kaldigi_yer'] ?? '',
        'yurt_ucreti'=>$ogrenci_basvuru_info['yurt_ucreti'] ?? '',
        'uyrugu'=>$ogrenci_basvuru_info['uyrugu'] ?? '',
        'diploma_notu'=> $egitim_bilgileri['diploma_notu'] ?? '',
        'yos_puani'=>$egitim_bilgileri['yos_puani'] ?? '',
        'gno'=>$egitim_bilgileri['gno'] ?? '',
        'ozel_durum'=>$aile_bilgileri['ozel_durum'] ?? '',
        'aile_aylik_geliri'=>$aile_bilgileri['aile_aylik_geliri'] ?? '',
        'ilkokul_sayi'=>$aile_bilgileri['ozel_durum_okul1'] ?? '',
        'ortaokul_sayi'=>$aile_bilgileri['ozel_durum_okul2'] ?? '',
        'lise_sayi'=>$aile_bilgileri['ozel_durum_okul3'] ?? '',
        'universite_sayi'=>$aile_bilgileri['ozel_durum_okul4'] ?? '',
        'bildigi_diller'=>$sosyal_faaliyetler['yabanci_dil'] ?? $bildigi_diller,
        'hobiler'=>$sosyal_faaliyetler['hobiler'] ?? $hobiler,
        'sosyal_faaliyetler'=>$sosyal_faaliyetler ?? '',
        'ozel_durumlar'=>$ozel_durumlar ?? '',
        'iban'=>$bank_account['iban'] ?? '',
        'bank_name'=>$bank_account['name'] ?? '',
        'bank_adsoyad'=>$bank_account['adsoyad'] ?? '',
        'dosyalar'=>$dosyalar ?? [],
        'date_added'=>date('Y-m-d H:i:s')];
        
        if ( !empty($ogrenci_info['anne_adi'])) {
            $data['anne_adi']=$ogrenci_info['anne_adi'];
        }else{
            $data['anne_adi']='';
        }
        
        if ( !empty($ogrenci_info['anne_soyadi'])) {
            $data['anne_soyadi']=$ogrenci_info['anne_soyadi'];
        }else{
            $data['anne_soyadi']='';
        }
        
        if ( !empty($ogrenci_info['is_durumu1'])) {
            $data['is_durumu1']=$ogrenci_info['is_durumu1'];
        }else{
            $data['is_durumu1']='';
        }
        
        if ( !empty($ogrenci_info['saglik_durumu1'])) {
            $data['saglik_durumu1']=$ogrenci_info['saglik_durumu1'];
        }else{
            $data['saglik_durumu1']='';
        }
        
        if ( !empty($ogrenci_info['anne_adi'])) {
            $data['baba_adi']=$ogrenci_info['baba_adi'];
        }else{
            $data['baba_adi']='';
        }
        
        if ( !empty($ogrenci_info['baba_soyadi'])) {
            $data['baba_soyadi']=$ogrenci_info['baba_soyadi'];
        }else{
            $data['baba_soyadi']='';
        }
        
        if ( !empty($ogrenci_info['is_durumu2'])) {
            $data['is_durumu2']=$ogrenci_info['is_durumu2'];
        }else{
            $data['is_durumu2']='';
        }
        
        if ( !empty($ogrenci_info['saglik_durumu2'])) {
            $data['saglik_durumu2']=$ogrenci_info['saglik_durumu2'];
        }else{
            $data['saglik_durumu2']='';
        }
        
        if ( !empty($ogrenci_info['kardes_sayisi'])) {
            $data['kardes_sayisi']=$ogrenci_info['kardes_sayisi'];
        }else{
            $data['kardes_sayisi']='';
        }
        
        if ( !empty($ogrenci_info['egitim_field'])) {
            $egitim_bilgileri=json_decode($ogrenci_info['egitim_field'], true);
        }else{
            $egitim_bilgileri=array();
        }
        
        $data['egitim_bilgileri']=@$egitim_bilgileri['l']['okul'];
        
        if(isset($this->request->get['burs_basvuru_id'])) {
            $data['burs_basvuru_id']=$this->request->get['burs_basvuru_id'];
        }else{
            $data['burs_basvuru_id']='';
        }
        
        $basvuru_info=$this->model_account_burs_basvuru->getBasvuru($this->request->get['burs_basvuru_id']);
     
        if (!empty($basvuru_info)) {
            $data['form_name']="BAŞVURU FORMU<br>". $basvuru_info['name'];
            $data['burs_decription']=html_entity_decode($basvuru_info['description'], ENT_QUOTES, 'UTF-8');
            $data['document_json'] = json_decode($basvuru_info['file'],true);
        }else{
            $data['form_name']='BURS BAŞVURU FORMU';
        }

        $this->load->model('tool/upload');

        $information=$this->model_account_customer->getInformation($table="taahhut_metni");
        $data['title']=$information['baslik'];
        $data['description']=html_entity_decode($information['text'], ENT_QUOTES, 'UTF-8');
        $data['text1']="<h4>Tebrikler! Burs başvurunuz başarıyla alınmıştır. Sürece dair güncellemeleri bu ekrandan takip edebilirsiniz</h4>";
        
        @$history =$this->model_account_burs_basvuru->getHistory($ogrenci_basvuru_info['burs_basvuru_ogrenci_id']);

        $status_array=[ 
            '0'=>'Başvuru alınmıştır',
            '2'=>'Değerlendirme Sürecinde',
            '1'=>'Burs İçin Uygun Görüldü',
            '3'=>'Revize talep edildi',
            '4'=>'Uygun Görülmedi',
            '5'=>'Başvuru Tamamlandı'
        ];

        $display_array=[ 
			'0'=>'none',
        	'1'=>'none',
        	'2'=>'none',
        	'3'=>'none',
        	'4'=>'none',
        	'5'=>'none',
        ];

        $data['histories']=[];
        
        if ( !empty($history)) {
            foreach ($history as $index=> $item) {
                $status_text=isset($status_array[$item['status']]) ? $status_array[$item['status']]: 'Bilinmeyen Durum';
                $data['histories'][]=[ 'sira'=>$index+1,
                'status'=>$status_text,
                'text'=>nl2br($item['text'])];
            }
        }
        
        $data['display'] = @$display_array[$ogrenci_basvuru_info['status']];
        
        if( !empty($ogrenci_basvuru_info['burs_basvuru_ogrenci_id'])) {
            $data['action']='edit';
            $status=$ogrenci_basvuru_info['status'];
        }else{
            $data['action']='add';
            $status=0;
        }
        
        $data['document_required'] = false;
        $data['display_bank'] = false;

        $data['status'] = $status;

        if ($basvuru_info['bank_select'] == 1 && empty(array_filter($bank_account))) {
                $data['display_bank'] = true;
        }elseif($basvuru_info['bank_select'] == 2 && $status == 1 && empty(array_filter($bank_account))) {
                $data['display_bank'] = true;
        }

        
        $destek_turu_array=[ 
			1=>'Tam Burs',
        	2=>'%50 Burs',
        	3=>'%25 Burs',
        	4=>'Market Desteği',
       	 	5=>'Diğer'
        ];

        $destek_etiketler=[];
        
        if ( !empty($ogrenci_basvuru_info['destek_turu'])) {
            $decoded=json_decode($ogrenci_basvuru_info['destek_turu'], true);
            
            if (is_array($decoded)) {
                foreach ($decoded as $durum_kodu) {
                    $durum_kodu_int=(int)$durum_kodu;
                    
                    if (isset($destek_turu_array[$durum_kodu_int])) {
                        $destek_etiketler[]=$destek_turu_array[$durum_kodu_int];
                    }
                }
            }
        }
        
        $history_last_info=$this->model_account_burs_basvuru->getHistoryLast(@$ogrenci_basvuru_info['burs_basvuru_ogrenci_id']);
        $bolum_class_string = '';
        $bolum_array = [];
        
        if (!empty($history_last_info['bolum'])) {
            $bolum_array=json_decode($history_last_info['bolum'], true);
            
            if (is_array($bolum_array)) {
                $bolum_class_string = implode(', .bolum-', $bolum_array);
            }
        }

         $data['form_basvuru'] = true;

         $today = date('Y-m-d');
         $dateEnd = $basvuru_info['date_end'];

        if (empty($ogrenci_basvuru_info) && ($basvuru_info['status'] == 0 || $dateEnd < $today)) {
            $data['form_basvuru'] = false;
        }

        $data['bolum_class_string'] = $bolum_class_string;
        $data['destek_turu'] = implode(', ', $destek_etiketler);
        $data['file_downlad'] = HTTP_SERVER.'storage/upload';

        $data['footer']=$this->load->controller('common/footer');
        $data['header']=$this->load->controller('common/header');

        $this->response->setOutput($this->load->view('account/basvuru_burs_form', $data));
    }
    
    public function save() {
        $json['error'] = array();
        
        if (!$this->customer->isLogged()) {
            $json['error']['warning']="Form gönderilirken bilinmeyen bir hata oluştu.";
            exit();
        }
        
        $this->load->model('account/burs_basvuru');


        if (empty($this->request->post['form'])) {
            $json['error']['warning']="Form gönderilirken bilinmeyen bir hata oluştu.";
        }

        $existing_application = $this->model_account_burs_basvuru->getBasvuruOgrenci((int)$this->request->post['burs_basvuru_id']);

        if (!empty($existing_application) && ((int)($this->request->post['burs_basvuru_ogrenci_id'] ?? 0) === 0 || (string)($this->request->post['form'] ?? '') === 'add')) {
            $this->request->post['form'] = 'edit';
            $this->request->post['burs_basvuru_ogrenci_id'] = (int)$existing_application['burs_basvuru_ogrenci_id'];
        }

        if ($this->request->post['form'] == 'add' && $this->model_account_burs_basvuru->hasBasvuru((int)$this->request->post['burs_basvuru_id'])) {
            $this->request->post['form'] = 'edit';
            $this->request->post['burs_basvuru_ogrenci_id'] = (int)$this->model_account_burs_basvuru->getBasvuruOgrenci((int)$this->request->post['burs_basvuru_id'])['burs_basvuru_ogrenci_id'];
        }

        if (empty($this->request->post['kaldigi_yer'])) {
            $json['error']['kaldigi_yer']="Kaldığınız yeri belirtiniz.";
        }
        
        if(isset($this->request->post['kaldigi_yer']) && $this->request->post['kaldigi_yer'] !='1'&& empty($this->request->post['yurt_ucreti'])) {
            $json['error']['yurt_ucreti']="Barınma ücretini yazınız.";
        }
        
        if (empty($this->request->post['dogum_yeri'])) {
            $json['error']['dogum_yeri']="Doğum yerini belirtiniz.";
        }
        
        if (empty($this->request->post['uyrugu'])) {
            $json['error']['uyrugu']="Uyruğunuzu Belirtiniz.";
        }
        
        if (empty($this->request->post['address'])) {
            $json['error']['address']="İkametgah edresi boş geçilemez.";
        }
        
        if (empty($this->request->post['egitim_bilgileri']['diploma_notu'])) {
            $json['error']['diploma_notu']="Diploma notunu yazınız.";
        }
        
        if (empty($this->request->post['egitim_bilgileri']['sinif'])) {
            $json['error']['sinif']="Lütfen sınıfınızı belirtiniz.";
        }
        
        if ( !isset($this->request->post['egitim_bilgileri']['gno']) || trim($this->request->post['egitim_bilgileri']['gno'])==='') {
            $json['error']['gno']="Genel not ortalamanızı yazınız.";
        }
        
        //AİLE BİLGİLERİ VALIDASYONU
        if (empty($this->request->post['aile_bilgileri']['anne_adi']) || empty($this->request->post['aile_bilgileri']['anne_soyadi'])) {
            $json['error']['aile']="Anne adı ve soyadı bilgilerinizi kontrol ediniz.";
        }
        
       
        if (empty($this->request->post['aile_bilgileri']['is_durumu1']) || empty($this->request->post['aile_bilgileri']['saglik_durumu1'])) {
            $json['error']['aile']="İş ve sağlık durumu bilgilerinizi kontrol ediniz.";
        }
        
       
        if (empty($this->request->post['aile_bilgileri']['baba_adi']) || empty($this->request->post['aile_bilgileri']['baba_soyadi'])) {
            $json['error']['aile']="Lütfen Baba ad ve soyadı bilgilerinizi kontrol ediniz.";
        }
        
     
        if (empty($this->request->post['aile_bilgileri']['is_durumu2']) || empty($this->request->post['aile_bilgileri']['saglik_durumu2'])) {
            $json['error']['aile']="Lütfen Baba iş ve sağlık durumu bilgilerinizi kontrol ediniz.";
        }
        
       
        if(isset($this->request->post['aile_bilgileri']['kardes_sayisi']) && ($this->request->post['aile_bilgileri']['kardes_sayisi'] > 0)) {
            if (empty($this->request->post['aile_bilgileri']['ozel_durum'])) {
                $json['error']['ozel_durum']="Kardeşlerin Eğitim düzeyleri boş geçilemez.";
            }
        }
        
        // KİŞİSEL GELİŞİM VE SOSYAL FAALİYETLER
        if (empty(trim($this->request->post['sosyal_faaliyetler']['yabanci_dil']))) {
            $json['error']['yabanci_dil']="Bildiğiniz diller boş geçilemez.";
        }
        
        if (empty(trim($this->request->post['sosyal_faaliyetler']['hobiler']))) {
            $json['error']['hobiler']="İlgi alanları boş geçilemez.";
        }
        
        if (empty(trim($this->request->post['sosyal_faaliyetler']['yetkinlikler']))) {
            $json['error']['yetkinlikler']="Yetkinlikler boş geçilemez.";
        }
        
        if (empty(trim($this->request->post['sosyal_faaliyetler']['kulup_tercihleri']))) {
            $json['error']['kulup_tercihleri']="Klüp tercihleri boş geçilemez.";
        }
        
        if (empty(trim($this->request->post['sosyal_faaliyetler']['gonullu_faliyetler']))) {
            $json['error']['gonullu_faliyetler']="Gönüllü faaliyetler boş geçilemez.";
        }
        
        if (empty(trim($this->request->post['sosyal_faaliyetler']['katki_alanlari']))) {
            $json['error']['katki_alanlari']="Katkı alanları boş geçilemez.";
        }
        
        
        $egitim_bilgileri_yuksekokul = [
            'universite' => $this->request->post['universite'],
            'fakulte' => $this->request->post['fakulte'],
            'bolum' => $this->request->post['bolum']
        ];

        $basvuru_info=$this->model_account_burs_basvuru->getBasvuru($this->request->post['burs_basvuru_id']);
        
        $required_array = json_decode($basvuru_info['file'],true);

        
        $missing_docs = [];

        foreach ($required_array as $key => $belge) {
            $dosya_key = 'file_' . $key;

            if ($this->request->post['document_required'][$key] == 1  && empty($this->request->post['dosyalar'][$dosya_key]) ) {
                $missing_docs[] = $belge['belge_adi'];
            }
        }

        if (!empty($missing_docs)) {
            $list = implode(', ', $missing_docs);
            $json['error']['file'] = "Lütfen (" . $list . ") " . (count($missing_docs) > 1 ? 'belgelerini' : 'belgesini') . " yükleyiniz.";
        }

        if ($this->request->post['bank_required']==1 && isset($this->request->post['bank_account']['iban'])) {
            $ibanRaw=$this->request->post['bank_account']['iban'];
            $iban=strtoupper(preg_replace('/\s+/', '', $ibanRaw)); // Tüm boşlukları kaldır ve büyük harfe çevir
            
            if (!preg_match('/^TR\d{24}$/', $iban)) {
                $json['error']['bank']="IBAN formatı geçersiz. TR ile başlamalı ve 26 hane olmalıdır.";
            }
        }
        
        if ($this->request->post['bank_required']==1 && empty(($this->request->post['bank_account']['name']))) {
            $json['error']['bank']="Lütfen bankanızı seçiniz.";
        }
        
        if ($this->request->post['bank_required'] == 1 && empty(($this->request->post['bank_account']['adsoyad']))) {
            $json['error']['bank']="Lütfen Hesap / Ad Soyad yazınız.";
        }
        
        if( !$json['error']) {
           $code = '';
           foreach ($egitim_bilgileri_yuksekokul as $key => $value) {
            $this->request->post['egitim_bilgileri'][$key] = $value;
           }

           if(isset($this->session->data['basvuru_code'])) {
            $code = $this->session->data['basvuru_code'];
           }

            if ($this->request->post['form']=='add') {
               $inserted = $this->model_account_burs_basvuru->addBasvuru($this->request->post['burs_basvuru_id'],$code, $this->request->post);
               if (is_numeric($inserted)) {
                    $this->request->post['form'] = 'edit';
                    $this->request->post['burs_basvuru_ogrenci_id'] = (int)$inserted;
               }
            }

            if ($this->request->post['form'] == 'edit') {
                $this->model_account_burs_basvuru->editBasvuru((int)$this->request->post['burs_basvuru_ogrenci_id'], $this->request->post);
            }

            unset($this->session->data['basvuru_code']);
            
            $this->session->data['success']="Kayıt başarıyla gerçekleştirildi.";
            $json['success']=$this->url->link('account/burs_basvuru', '', true);
        }else{
            $json['error']['warning']="Lütfen Formdaki hataları dikkatlice kontrol ediniz. (*) alanlar zorunludur.";
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    public function upload() {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', '', true));
        }
        
        $this->load->language('tool/upload');
        $json=array();
        
        if (!$json) {
            if (!empty($this->request->files['file']['name']) && is_file($this->request->files['file']['tmp_name'])) {
                // Sanitize the filename
                $filename=basename(html_entity_decode(utf8_strtolower(dosya_temizle($this->request->files['file']['name'])), ENT_QUOTES, 'UTF-8'));
                
                // Validate the filename length
                if ((utf8_strlen($filename) < 3) || (utf8_strlen($filename) > 128)) {
                    $json['error']=$this->language->get('error_filename');
                }
                
                // Allowed file extension types
                $allowed=array();
                $extension_allowed=preg_replace('~\r?\n~', "\n", $this->config->get('config_file_ext_allowed'));
                $filetypes=explode("\n", $extension_allowed);
                
                foreach ($filetypes as $filetype) {
                    $allowed[]=trim($filetype);
                }
                
                if (!in_array(strtolower(substr(strrchr($filename, '.'), 1)), $allowed)) {
                    $json['error']=$this->language->get('error_filetype');
                }
                
                // Allowed file mime types
                $allowed=array();
                $mime_allowed=preg_replace('~\r?\n~', "\n", $this->config->get('config_file_mime_allowed'));
                $filetypes=explode("\n", $mime_allowed);
                
                foreach ($filetypes as $filetype) {
                    $allowed[]=trim($filetype);
                }
                
                if ( !in_array($this->request->files['file']['type'], $allowed)) {
                    $json['error']=$this->language->get('error_filetype');
                }
                
                // Check to see if any PHP files are trying to be uploaded
                $content=file_get_contents($this->request->files['file']['tmp_name']);
                
                if (preg_match('/\<\?php/i', $content)) {
                    $json['error']=$this->language->get('error_filetype');
                }
                
                // Return any upload error
                if ($this->request->files['file']['error'] !=UPLOAD_ERR_OK) {
                    $json['error']=$this->language->get('error_upload_'. $this->request->files['file']['error']);
                }
            } else {
                $json['error']=$this->language->get('error_upload');
            }
        }
        
        if (!$json) {
            $code =  token(32);
			$file = $filename . '.' .$code;
            $file=$filename . '.'. token(32);
            move_uploaded_file($this->request->files['file']['tmp_name'], DIR_DOWNLOAD . $file);
            $json['filename']=$filename;
            $json['mask']=$file;
            $json['filetype']=strtolower(substr(strrchr($filename, '.'), 1));
            $this->load->model('tool/upload');
            $json['code']=$this->model_tool_upload->addUpload($filename, $file,$code,'basvuru_burs', 'b2', $this->request->get['burs_basvuru_id']);
    
            $json['success']=$this->language->get('text_upload');
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    public function onay() {
        $json=array();
        $json['error']=array();
        
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', '', true));
        }
        
        $this->load->language('account/burs_basvuru');
        $this->load->model('account/burs_basvuru');
        $this->load->model('account/customer');
        
        if(isset($this->request->get['burs_basvuru_id'])) {
            $basvuru_info=$this->model_account_burs_basvuru->getBasvuru($this->request->get['burs_basvuru_id']);
            $question=json_decode($basvuru_info['custom_field'], true);
            $soru_sayisi=count($question);
            
            if( !empty($basvuru_info['filename']) && empty($this->request->post['file'])) {
                $json['error']['file']='Lütfen istenen dosyayı yükleyiniz';
            }
            
            if ((@count(@array_filter($this->request->post['question'])) < $soru_sayisi) && !empty($question)) {
                $json['error']['question']='Lütfen formda yer alan soruları cevaplayınız.';
            }
            
            $basvuru_status=$this->model_account_burs_basvuru->getCustomerBasvuru($this->request->get['burs_basvuru_id']);
            
            if( !empty($basvuru_status)) {
                $json['error']['status']=true;
                $json['error']['text']="Bu başvuruya daha önce başvuru yaptınız.";
            }
            
            if(!$json['error']) {
                $json['burs_basvuru_id']=$this->request->get['burs_basvuru_id'];
                $store_info=$this->model_account_customer->getStorestoreId($this->customer->getStoreId());
                $this->model_account_burs_basvuru->addBasvuru($this->request->get['burs_basvuru_id'], $this->request->post);
                $data['text_baslik']=$basvuru_info['name'];
                $data['text_message']=sprintf($this->language->get('text_message'), $store_info['name']);
                
                if($basvuru_info['mail_geri_bildirim']) {
                    $mail = new Mail($this->config->get('config_mail_engine'));
                     $mail->parameter = $this->config->get('config_mail_parameter');
                    $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
                    $mail->smtp_username = $this->config->get('config_mail_smtp_username');
                    $mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
                    $mail->smtp_port = $this->config->get('config_mail_smtp_port');
                    $mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

                    $mail->setTo($this->customer->getEmail());
                    $mail->setFrom($this->config->get('config_mail_parameter'));
                    $mail->setSender(html_entity_decode($store_info['name'], ENT_QUOTES, 'UTF-8'));
                    $mail->setSubject(html_entity_decode(sprintf($this->language->get('text_subject'), $store_info['name']), ENT_QUOTES, 'UTF-8'));
                    $mail->setText($this->load->view('mail/basvuru', $data));
                    //$mail->send();
                }
                
                $json['success']="Başvuru işlemi başarıyla gerçekleştirildi.";
            }
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    protected function share($code) {
        $this->load->model('account/burs_basvuru');

        $bolum = 'burs';
        $info = $this->model_account_burs_basvuru->getBasvuruSHare($code, $bolum);

        if (!empty($info['code'])) {
            return $info['code'];
        } else {
            return '';
        }
    }
    
    public function deletefileUpload() {
        $json=array();
        
        if(isset($this->request->get['code'])) {
            $this->load->model('tool/upload');
            $this->model_tool_upload->deleteUpload($this->request->get['code']);
            $json['success']=$this->language->get('text_success');
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}