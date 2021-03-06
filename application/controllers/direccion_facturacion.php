<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

include ('api.php');

class Direccion_Facturacion extends CI_Controller {
	var $title = 'Direcci&oacute;n de Facturaci&oacute;n'; 		
	var $subtitle = 'Direcci&oacute;n de Facturaci&oacute;n'; 	
	var $mensaje= '';					
	var $reg_errores = array();		//validación para los errores
	
	private $id_cliente;	
	 
	public static $TIPO_DIR = array(
		"RESIDENCE"	=> 0, 
		"BUSINESS"	=> 1, 
		"OTHER"		=>	2
	);
	 
	function __construct() {
        // Call the Model constructor
        parent::__construct();
		
		$this->output->nocache();
		
		//si no hay sesión
		//manda al usuario a la... página de login
		$this->redirect_cliente_invalido('id_cliente', 'login');
		
		//cargar el modelo en el constructor
		$this->load->model('direccion_facturacion_model', 'direccion_facturacion_model', true);
		$this->load->model('direccion_envio_model', 'direccion_envio_model', true);
		//la sesion se carga automáticamente
		
		//si la sesión se acaba de crear, toma el valor inicializar el id del cliente de la session creada en el login/registro
		$this->id_cliente = $this->session->userdata('id_cliente');
		
		$this->api= new Api();
		
    }

	public function index() {		
		if ($_POST) {
			if (array_key_exists('direccion_selecionada', $_POST))
				$this->session->set_userdata('dir_envio', $_POST['direccion_selecionada']);
			if ($this->session->userdata('redirect_to_order'))
				redirect("orden_compra");
		}
		$this->registrar_rs();
	}
		
	public function registrar_rs($nueva="") {
		if($nueva=="nueva"){
			$data['nueva_rs'] = TRUE;
		}				
		
		$id_cliente = $this->id_cliente;						
		$data['title']=$this->title;
		$data['subtitle'] = "Si requieres factura, selecciona los datos de facturaci&oacute;n";
		$data['mensaje']='';											 
		
		//recuperar el listado de las direcciones del cliente
		$data['lista_direcciones'] = $this->direccion_facturacion_model->listar_razon_social($id_cliente);						
		$data['registrar_rs'] = TRUE;		//para indicar que se debe mostrar formulario de registro
		$data['solicita_factura'] = TRUE;						
		
		$script_file = "<script type='text/javascript' src='". base_url() ."js/dir_facturacion.js'> </script>";
		$data['script'] = $script_file;
		
		if ($_POST)	{	//si hay parámetros del formulario								
			$form_values = array();	//alojará los datos previos a la inserción	
			$form_values = $this->get_datos_rs();			
			$consecutivo_rs= $this->direccion_facturacion_model->get_consecutivo_rs($id_cliente);																						
			if(empty($this->reg_errores)){																												   															
			    $id_rs=$this->direccion_facturacion_model->insertar_rs($form_values['direccion']);															
				$datars=array(
					'id_rs'=>$id_rs							
				);						
				$this->session->set_userdata($datars);
				$this->session->userdata('id_rs');
				if(array_key_exists('chk_default', $_POST) || $consecutivo_rs == 0) {
							$this->direccion_facturacion_model->establecer_predeterminado_rs($id_cliente, $id_rs);	
							$form_values['direccion']['id_estatusSi'] = 3;
				}	
				redirect('direccion_facturacion/registrar_direccion', 'location', 303);																										
										 																							
			}	
			else{
				$data['reg_errores'] = $this->reg_errores;		
				$data['nueva_rs'] = TRUE;						
				$this->cargar_vista('', 'direccion_facturacion' , $data);	
			}			 
		}
		else {
			$this->cargar_vista('', 'direccion_facturacion' , $data);
		}
	}

	public function editar_rs($consecutivo = 0){			
			
			$id_cliente = $this->id_cliente;
				
			$script_file = "<script type='text/javascript' src='". base_url() ."js/dir_facturacion.js'></script>";
			$data['script'] = $script_file;
			
			$data['mensaje']='';
			$data['editar_rs'] = TRUE;
			//no registradass
			if (!$consecutivo && $this->session->userdata("dir_facturacion")) {
				$facturacion_en_sesion = $this->session->userdata("dir_facturacion");
				
				$dir_facturacion = null;
				/*crear los objetos para la edición tc*/
				foreach ($facturacion_en_sesion as $key => $value) {
					$dir_facturacion->$key = $value;
				}
				//var_dump($dir_facturacion);
				//exit();
				$dir_facturacion->id_consecutivoSi = 0;	//el id_consecutivoSi (debe ser 0)
				$datos_direccion = $dir_facturacion;
					
			} else {
				$datos_direccion = $this->direccion_facturacion_model->obtener_rs($consecutivo);
			}
			
			if ($consecutivo) {
						
				$data['title'] = $this->title;
				$data['subtitle'] = "Edita los campos que quieras modificar";
				$data['consecutivo']=$consecutivo;
	
				$data['datos_direccion'] = $datos_direccion;				
		
				$lista_paises_think = $this->direccion_facturacion_model->listar_paises_think();
				$data['lista_paises_think'] = $lista_paises_think;
																								
				if($_POST){					
					$form_values = array();	//alojará los datos previos a la inserción	
					$form_values = $this->get_datos_rs();
						
					if (empty($this->reg_errores)) {
							
						$form_values['direccion']['id_razonSocialIn'] = $consecutivo;																								
						
						if (!$consecutivo) {
							$direccion = $form_values['direccion'];
							$this->cargar_en_session($direccion);
							
							$msg_actualizacion = "Tu RFC ha sido actualizado exitosamente";
							$data['msg_actualizacion'] = $msg_actualizacion;							
														
						} 
						else {
							$this->direccion_facturacion_model->actualizar_rs($consecutivo, $form_values['direccion']);
							if(array_key_exists('chk_default', $_POST) || $consecutivo == 0) {
								$this->direccion_facturacion_model->establecer_predeterminado_rs($id_cliente, $consecutivo);	
								$form_values['direccion']['id_estatusSi'] = 3;
							}							
							$this->cargar_en_session($consecutivo);		
							if (array_key_exists('guardar_usar_otra', $_POST)) {
								redirect('direccion_facturacion');
							}	
							else{
								redirect('direccion_facturacion/registrar_direccion');
							}												
						}
					}
					else{								
						$data['reg_errores'] = $this->reg_errores;				
						$this->cargar_vista('', 'direccion_facturacion' , $data);	
					}																			
				}	
				else{
					$this->cargar_vista('', 'direccion_facturacion', $data);	
				}								
			}
								
	}

	public function eliminar_rs($id_rs = ''){		
		$id_cliente = $this->id_cliente;
		$data['title'] = $this->title;		
		$data['subtitle'] = ucfirst('Eliminar Direcci&oacute;n');
		$this->direccion_facturacion_model->eliminar_rs($id_rs);
		redirect('direccion_facturacion');			
	}
	
	public function registrar_direccion($nueva=""){
						
		if($nueva=="nueva"){
			$data['nueva_direccion'] = TRUE;
		}				
		$data['registrar_direccion'] = TRUE;		//para indicar que se debe mostrar formulario de registro		
		
		if ($_POST) {
			if (array_key_exists('razon_social_seleccionada', $_POST)) {				
				$this->session->set_userdata('razon_social', $_POST['razon_social_seleccionada']);
				$this->session->set_userdata('requiere_factura', 'si');						
			}						
		} 
		else if ($this->session->userdata('id_rs')!=""){			
			$this->session->set_userdata('razon_social', $this->session->userdata('id_rs'));		
			$this->session->set_userdata('requiere_factura', 'si');					 		
		}
										
		$id_rs=$this->session->userdata('razon_social');
		
		$id_cliente = $this->id_cliente;	
		$data['title']=$this->title;
		$data['subtitle'] = "Selecciona una direcci&oacute;n de facturaci&oacute;n";	
		$data['mensaje']='';	
		
		$data['dir_envio']=$this->direccion_envio_model->listar_direcciones($id_cliente);		
		
		
		$lista_paises_think = $this->direccion_facturacion_model->listar_paises_think();
		$data['lista_paises_think'] = $lista_paises_think;						 
		
		//recuperar el listado de las direcciones del cliente
		$data['lista_direcciones'] = $this->direccion_facturacion_model->listar_direcciones($id_cliente);
		
		
		$script_file = "<script type='text/javascript' src='". base_url() ."js/dir_facturacion.js'> </script>";
		$data['script'] = $script_file;
		
		if ($_POST)	{	//si hay parámetros del formulario
											
			$form_values = array();	//alojará los datos previos a la inserción	
			$form_values = $this->get_datos_direccion();			
			$consecutivo=$this->direccion_facturacion_model->get_consecutivo($id_cliente);			
			$form_values['direccion']['id_clienteIn'] = $id_cliente;
			$form_values['direccion']['id_consecutivoSi'] = $consecutivo + 1;		//cambió
			$form_values['direccion']['address_type'] = self::$TIPO_DIR['BUSINESS'];		//address_type			
			
			if(empty($this->reg_errores)){				
				$id_dir = $this->direccion_facturacion_model->insertar_direccion($form_values['direccion']);
				$datadire=array(
							'id_dir'=>$id_dir							
						);
				$this->session->set_userdata($datadire);	
				if(array_key_exists('chk_default', $_POST) || $consecutivo == 0) {
							$this->direccion_facturacion_model->establecer_predeterminado($id_cliente, $id_dir);	
							$form_values['direccion']['id_estatusSi'] = 3;
				}	
				
				$this->load->helper('date');
				$fecha=mdate('%Y/%m/%d',time());
				$datadir = array(
       				'id_clienteIn'  => $id_cliente,
       				'id_consecutivoSi' => $consecutivo,
       				'id_razonSocialIn' => $id_rs,
       				'fecha_registroDt' => $fecha                    				                    		
   				);																										
				if($id_rs!="" && $consecutivo != ""){
					$rbr = $this->direccion_facturacion_model->busca_relacion($id_cliente, $id_rs, $consecutivo);				
					if ($rbr->num_rows() == 0) {					
						$this->load->helper('date');
						$fecha = mdate('%Y/%m/%d',time());
						$data_dir = array(
                   			'id_clienteIn'  => $id_cliente,
                   			'id_consecutivoSi' => $consecutivo,
                   			'id_razonSocialIn' => $id_rs,
                   			'fecha_registroDt' => $fecha                    				                    		
               			);																										
						$this->direccion_facturacion_model->insertar_rs_direccion($data_dir);		
						$this->session->set_userdata('requiere_factura', 'si');	
					}	
				}
					
				redirect('orden_compra', 'location', 303);																														 
			}	
			else{
				$data['reg_errores'] = $this->reg_errores;
				if (!array_key_exists('razon_social_seleccionada', $_POST)) {			
					$data['nueva_direccion'] = TRUE;		
				}
				$this->cargar_vista('', 'direccion_facturacion' , $data);	
			}
		}
		else {
			$this->cargar_vista('', 'direccion_facturacion' , $data);
		}
	}

	public function editar_direccion($consecutivo = 0){
			$id_cliente = $this->id_cliente;
				
			$script_file = "<script type='text/javascript' src='". base_url() ."js/dir_facturacion.js'></script>";
			$data['script'] = $script_file;
			
			$data['mensaje']='';
			$data['editar_direccion'] = TRUE;
			//no registradass
			if (!$consecutivo && $this->session->userdata("dir_facturacion")) {
				$facturacion_en_sesion = $this->session->userdata("dir_facturacion");
				
				$dir_facturacion = null;
				/*crear los objetos para la edición tc*/
				foreach ($facturacion_en_sesion as $key => $value) {
					$dir_facturacion->$key = $value;
				}
				//var_dump($dir_facturacion);
				//exit();
				$dir_facturacion->id_consecutivoSi = 0;	//el id_consecutivoSi (debe ser 0)
				$datos_direccion = $dir_facturacion;
					
			} else {
				$datos_direccion = $this->direccion_facturacion_model->obtener_direccion($id_cliente, $consecutivo);
			}
			
			if ($consecutivo) {
						
				$data['title'] = $this->title;
				$data['subtitle'] = "Edita los campos que quieras modificar";
				$data['consecutivo']=$consecutivo;
	
				$data['datos_direccion'] = $datos_direccion;				
		
				$lista_paises_think = $this->direccion_facturacion_model->listar_paises_think();
				$data['lista_paises_think'] = $lista_paises_think;
																								
				if($_POST){					
					$form_values = array();	//alojará los datos previos a la inserción	
					$form_values = $this->get_datos_direccion();
						
					if (empty($this->reg_errores)) {
							
						$form_values['direccion']['id_ConsecutivoSi'] = $consecutivo;
																														
						if (!$consecutivo) {
							$direccion = $form_values['direccion'];
							$this->cargar_en_session($direccion);
							
							$msg_actualizacion = "Tu dirección ha sido actualizada exitosamente";
							$data['msg_actualizacion'] = $msg_actualizacion;							
														
						} 
						else {
							$this->direccion_facturacion_model->actualizar_direccion($id_cliente,$consecutivo, $form_values['direccion']);
							if(array_key_exists('chk_default', $_POST)) {
								$this->direccion_facturacion_model->establecer_predeterminado($id_cliente, $consecutivo);	
								$form_values['direccion']['id_estatusSi'] = 3;
							}							
							$this->cargar_en_session($consecutivo);	
							if (array_key_exists('guardar_usar_otra', $_POST)) {
								redirect('direccion_facturacion/registrar_direccion');
							}	
							else{
								redirect('orden_compra');								
							}						
							
						}
					}
					else{								
						$data['reg_errores'] = $this->reg_errores;				
						$this->cargar_vista('', 'direccion_facturacion' , $data);	
					}																			
				}	
				else{
					$this->cargar_vista('', 'direccion_facturacion', $data);	
				}								
			}
								
	}

	public function eliminar_direccion($consecutivo = ''){		
		$id_cliente = $this->id_cliente;
		$data['title'] = $this->title;		
		$data['subtitle'] = ucfirst('Eliminar Direcci&oacute;n');
		$this->direccion_facturacion_model->eliminar_direccion($id_cliente, $consecutivo);
		redirect('direccion_facturacion/registrar_direccion');			
	}
	
	private function get_datos_rs(){
		$datos = array();		 		
		
		if(!empty($_POST['txt_rfc'])){
			if((strlen($_POST['txt_rfc'])>13)||(strlen($_POST['txt_rfc'])<12)){
			    $this->reg_errores['txt_rfc'] = '<span class="error">Por favor ingresa tu RFC</span>';		
			}	
			else{
				if(strlen($_POST['txt_rfc'])==12){
					if (preg_match('/^([a-zA-Z]{3})+([0-9]{6})+([a-zA-Z0-9]{3})$/', $_POST['txt_rfc'])) {
						$datos['direccion']['tax_id_number'] = $_POST['txt_rfc'];
					}
					else {
						$this->reg_errores['txt_rfc'] = '<span class="error">Por favor ingresa tu RFC</span>';
					}	
				}
				else if(strlen($_POST['txt_rfc'])==13){					    
					if (preg_match('/^([a-zA-Z]{4})+([0-9]{6})+([a-zA-Z0-9]{3})$/', $_POST['txt_rfc'])) {
						$datos['direccion']['tax_id_number'] = $_POST['txt_rfc'];
					}
					else {
						$this->reg_errores['txt_rfc'] = '<span class="error">Por favor ingresa tu RFC</span>';
					}
				}
			}
		}
		else{
			$this->reg_errores['txt_rfc'] = '<span class="error">Por favor ingresa tu RFC</span>';				
		}
		if(!empty($_POST['txt_razon_social'])){
			$datos['direccion']['company'] = $_POST['txt_razon_social'];
		}
		else{
			$this->reg_errores['txt_razon_social'] = '<span class="error2">Por favor ingresa tu nombre o razón social</span>';
		}
		if(!empty($_POST['txt_email'])){
			if (preg_match('/^[^0-9][a-zA-Z0-9_]+([.][a-zA-Z0-9_]+)*[@][a-zA-Z0-9_]+([.][a-zA-Z0-9_]+)*[.][a-zA-Z]{2,4}$/', $_POST['txt_email'])) {    		
				$datos['direccion']['email'] = $_POST['txt_email'];					
			}	
		}		  	
		else{
			$this->reg_errores['txt_email'] = '<span class="error2">Por favor ingresa un correo electrónico válido. Ejemplo: nombre@dominio.mx</span>';
		}	
		if (array_key_exists('chk_default', $_POST)) {
			$datos['direccion']['id_estatusSi'] = 3;	//indica que será la razon social predeterminada			
		}
		else{
			$datos['direccion']['id_estatusSi'] = 1;	//indica que será la razon social predeterminada
		}			
		$datos['direccion']['id_clienteIn'] = $this->id_cliente;			
		return $datos;
	}
	
	private function get_datos_direccion(){
		
		if(!empty($_POST['txt_calle'])){
			$datos['direccion']['address1'] = $_POST['txt_calle'];
		}
		else{
			$this->reg_errores['txt_calle'] = '<span class="error">Por favor ingresa una calle</span>';
		}
		if(!empty($_POST['txt_numero'])){
			$datos['direccion']['address2'] = $_POST['txt_numero'];
		}
		else{
			$this->reg_errores['txt_numero'] = '<span class="error">Por favor ingresa el número exterior</span>';
		}		
		if(!empty($_POST['txt_cp'])){
			if(preg_match('/^[0-9]{5,5}([- ]?[0-9]{4,4})?$/', $_POST['txt_cp'])){
			    $datos['direccion']['zip'] = $_POST['txt_cp'];	
			}			
			else{
			    $this->reg_errores['txt_cp'] = '<span class="error2">Por favor ingresa un código postal de 5 dígitos</span>';	
			}
		}
		else{
			$this->reg_errores['txt_cp'] = '<span class="error2">Por favor ingresa un código postal de 5 dígitos</span>';
		}
		if(!empty($_POST['txt_colonia'])){
			$datos['direccion']['address3'] = $_POST['txt_colonia'];
		}
		else{
			$this->reg_errores['txt_colonia'] = '<span class="error">Por favor ingresa la colonia</span>';
		}
		if(!empty($_POST['txt_ciudad'])){
			$datos['direccion']['city'] = $_POST['txt_ciudad'];
		}
		else{
			$this->reg_errores['txt_ciudad'] = '<span class="error">Por favor ingresa la ciudad</span>';
		}
		if(!empty($_POST['txt_estado'])){
			$datos['direccion']['state'] = $_POST['txt_estado'];
		}
		else{
			$this->reg_errores['txt_estado'] = '<span class="error">Por favor ingresa el estado</span>';
		}													
		
		if(array_key_exists('txt_num_int', $_POST)){
		    $datos['direccion']['address4'] = $_POST['txt_num_int'];	
		}
		
		if(array_key_exists('sel_pais', $_POST)){
			$datos['direccion']['codigo_paisVc'] = $_POST['sel_pais'];
		}			
																
		if (array_key_exists('chk_default', $_POST)) {
			$datos['direccion']['id_estatusSi'] = 3;	//indica que será la direccion de facturacion predeterminada			
		}
		else{
			$datos['direccion']['id_estatusSi'] = 1;	//indica que será la direccion de facturacion predeterminada
		}
									 	
		return $datos;
	}
	
	public function requiere_factura(){		
			$datars=array(
				'requiere_factura'=>'no',
				'id_dir'=>NULL,
				'id_rs'=>NULL											
			);						
			$this->session->set_userdata($datars);
			redirect('orden_compra');					
	}
	
	private function cargar_en_session($direccion = null)
	{
		if (is_array($direccion)) { //si no se guarda en BD
			$this->session->set_userdata('dir_facturacion', $direccion);
		} else if ( ((int)$direccion) != 0 && is_int((int)$direccion)) {	//si ya está regiustrada la direccion en BD sólo sube el consecutivo
			$this->session->set_userdata('dir_facturacion', $direccion);
		} else {	//si no es ninguno de los dos, elimina el elemento de la sesión
			$this->session->unset_userdata('dir_facturacion');
		}
	}
				
	private function cargar_vista($folder, $page, $data){	
		//Para automatizar un poco el desplieguee
		$this->load->view('templates/header', $data);
		$this->load->view('templates/menu.html', $data);
		if ($this->session->userdata('promociones') && $this->session->userdata('promocion')) {
			$data['detalle_promociones']=$this->api->obtiene_articulos_y_promociones();					
			$this->load->view('templates/promocion.html', $data);															
		}
		$this->load->view($folder.'/'.$page, $data);
		$this->load->view('templates/footer', $data);
	}
	
	private function redirect_cliente_invalido($revisar = 'id_cliente', $destino = 'login', $protocolo = 'http://'){
		if (!$this->session->userdata($revisar)) {
			//$url = $protocolo . BASE_URL . $destination; // Define the URL.
			$url = site_url( $destino);  // Define the URL.
			header("Location: $url");
			exit(); // Quit the script.
		}
	}
}

/* End of file direccion_facturacion.php */
/* Location: ./application/controllers/direccion_facturacion.php */