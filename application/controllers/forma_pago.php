<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

include ('dtos/Tipos_Tarjetas.php');
include ('api.php');

class Forma_Pago extends CI_Controller {
	var $title = 'Forma de Pago'; 		// Capitalize the first letter
	var $subtitle = 'Selecciona una forma de pago'; 	// Capitalize the first letter
	var $reg_errores = array();		//validación para los errores
	
	//var $tc = array();
	private $id_cliente;
	private $consecutivo_tc = 0;
	//protected $lista_bancos = array();
	 
	function __construct()
    {
        //Call the Model constructor
        parent::__construct();
		
		$this->output->nocache();
		
		//si no hay sesión
		//manda al usuario a la... pagina de login
		$this->redirect_cliente_invalido('id_cliente', 'login');
		
		//cargar el modelo en el constructor
		$this->load->model('forma_pago_model', 'forma_pago_model', true);		

		//si la sesión se acaba de crear, toma el valor inicializar el id del cliente de la session creada en el login/registro
		$this->id_cliente = $this->session->userdata('id_cliente');
		
		$this->api= new Api();
		//echo "requiere_envio: " . $this->session->userdata('requiere_envio');
    }

	public function index()	//Para pruebas se usa 1
	{
		$this->listar();
	}
	
	/**
	 * Lista las tarjetas registradas
	 * */
	public function listar($msg = '', $redirect = TRUE)
	{
		
		$data['title'] = $this->title;
		$data['subtitle'] = $this->subtitle;		
		$data['mensaje'] = $msg;
		$data['redirect'] = $redirect;
		
		//listar por default las tarjetas del cliente
		$data['lista_tarjetas'] = $this->forma_pago_model->listar_tarjetas($this->id_cliente);
			
		//cargar vista
		if($data['lista_tarjetas']->num_rows()>0){	
			$this->cargar_vista('', 'forma_pago', $data);
		}
		else{
			$this->registrar('tc');
		}		
		
		//Se elimina de session por seguridad
		if ($this->session->userdata('tarjeta'))
			$this->session->unset_userdata('tarjeta');
	}
	
	/**
	 * Coloca la forma de pago (tarjeta/deposito) en session
	 */
	public function seleccionar($forma_pago='') {
		if (!empty($forma_pago)) {
			//Registrar en session y calcular el siguiente destino
			if ($_POST) {
				if (array_key_exists('tajeta_selecionada', $_POST)) {
					$this->session->set_userdata('tarjeta', $_POST['tajeta_selecionada']);
					$this->session->unset_userdata('deposito');
				} else if (array_key_exists('deposito_bancario', $_POST)) {
					$this->session->set_userdata('deposito', TRUE);
					$this->session->unset_userdata('tarjeta');
				}
				
				//Control de Flujo
				//Para calcular destino siguiente y actualizxarlo en sesión
				$destino = $this->obtener_destino();
				
				redirect($destino, 'location', 303);
				//exit();
			}
		} else {
			//ir al listado
			redirect("forma_pago/listar", "location", 303);
			//exit();
		}		
	}
	
	/**
	 * Registrar la informacion de la TC
	 */
	public function registrar($tipo = 'tc')
	{
		$id_cliente = $this->id_cliente;
		
		$consecutivo = $this->forma_pago_model->get_consecutivo($id_cliente);
		
		$data['title'] = $this->title;
		$data['subtitle'] = $this->subtitle;
		$data['lleva_ra'] = $this->session->userdata('lleva_ra');	//para la renovación automática
		
		//catálogo que se obtendrá del CCTC
		if ($tipo == "tc") {
			$lista_tipo_tarjeta = $this->listar_tipos_tarjeta();	//listar_tipos_tarjeta_WS();
			$data['lista_tipo_tarjeta'] = $lista_tipo_tarjeta;
		} else if ($tipo == "amex") {
			//catálogo de paises de think
			$lista_paises_amex = $this->forma_pago_model->listar_paises_amex();
			$data['lista_paises_amex'] = $lista_paises_amex;
		}
		
		$script_file = "<script type='text/javascript' src='". base_url() ."js/forma_pago.js'></script>";
		$data['script'] = $script_file;
				
		//recuperar el listado de las tarjetas del cliente
		$data['lista_tarjetas'] = $this->forma_pago_model->listar_tarjetas($id_cliente);
		
		$data['form'] = $tipo;		//para indicar qué formulario mostrar
		
		if ($_POST && empty($this->reg_errores))	{	//si hay parámetros del formulario
			if ($tipo == "tc") {
				$this->registrar_tc($id_cliente);
			} else if ($tipo == "amex") {
				$this->registrar_amex($id_cliente);
			}
		} else if (!empty($this->reg_errores)){	//Si hubo errores en los datos enviados
			$data['reg_errores'] = $this->reg_errores;
			$this->cargar_vista('', 'forma_pago' , $data);
		} else {
			if ($tipo == "amex") $data['subtitle'] = "Ingresa o edita tu direcci&oacute;n de tarjeta AMEX";
			$this->cargar_vista('', 'forma_pago' , $data);
		}
	}
	
	/*
	 * Registro de la información de la tarjeta
	 */
	private function registrar_tc($id_cliente) {
		$data['title'] = $this->title;
		$data['subtitle'] = 'Selecciona una forma de pago';
		
		$consecutivo = $this->forma_pago_model->get_consecutivo($id_cliente);
		$lista_tipo_tarjeta = $this->forma_pago_model->listar_tipos_tarjeta();	//$this->listar_tipos_tarjeta_WS();
		
		$form_values = array();		
		$form_values = $this->get_datos_tarjeta();
		
		$form_values['tc']['id_clienteIn'] = $id_cliente;
		$form_values['tc']['id_TCSi'] = $consecutivo + 1;
		
		if (empty($this->reg_errores)) {
			
			//si no hay errores configurar la información de la tarjeta
			$form_values['tc']['descripcionVc'] = $this->get_descripcion_tarjeta($form_values['tc']['id_tipo_tarjetaSi'], $lista_tipo_tarjeta);
			$form_values['amex'] = null;	//para que no se tome encuenta por el momento
			
			$tipo = $form_values['tc']['id_tipo_tarjetaSi'];	//1 es AMEX
			/*if ($tipo != 1) {
				$form_values['amex'] = null;	//para que no se tome encuenta por el momento
			} else {
				$form_values['amex']['id_clienteIn'] = $id_cliente;
				$form_values['amex']['id_TCSi'] = $consecutivo + 1;
			}
			
			echo "<pre>";
			print_r($form_values);
			echo "<pre>";
			exit;*/
			//si no hay errores y se solicita registrar la tarjeta en BD
			if (isset($form_values['tc']['id_estatusSi'])) {
				//verificar que no exista la tarjeta activa en la BD
				$num_completo = $form_values['tc']['terminacion_tarjetaVc'];
				$num_temp = substr($num_completo, strlen($num_completo) - 4);
				$primer_digito = substr($num_completo, 0, 1);	// primer dígito de la tarjeta
				$form_values['tc']['terminacion_tarjetaVc'] = $num_temp;
				
				if ($this->forma_pago_model->existe_tc($form_values['tc'])) {	
					//Redirect al listado por que ya existe
					$this->listar("La tarjeta ya está registrada.", FALSE);
				} else {
					//verifica si hay o no dirección activa predeterminada
					$existe_predeterminada = $this->existe_predetereminada($id_cliente);
						
					//sólo la primera que se registra se predetermina
					if (isset($form_values['predeterminar']) || $consecutivo ==  0 || !$existe_predeterminada) {
						$this->forma_pago_model->quitar_predeterminado($id_cliente);
						$form_values['tc']['id_estatusSi'] = 3;
					}
					//para registrar en CCTC
					$form_values['tc']['terminacion_tarjetaVc'] = $num_completo;
					
					//se manda insertar en CCTC
					//if ($this->registrar_tarjeta_CCTC($form_values['tc'], $form_values['amex'])) {	//Se registró exitosamente! en CCTC";
					if ($this->registrar_tarjeta_interfase_CCTC($form_values['tc'], $form_values['amex'])) {	//Se registró exitosamente! en CCTC";
						//sólo registrar los últimos 4 dígitos de la TC, pra el registro en la bd de ecommerce
						$form_values['tc']['terminacion_tarjetaVc'] = $num_temp;
						$form_values['tc']['primer_digitoTi'] = $primer_digito;
						//registrar localmente
						if ($this->forma_pago_model->insertar_tc($form_values['tc'])) {
							$this->consecutivo_tc = $form_values['tc']['id_TCSi'];	//cual  es el que se registra
							//Verificar el flujo() => cargar o no en session y redireccionar
							$this->cargar_en_session($form_values['tc']['id_TCSi']);
							
							//Para calcular destino siguiente y actualizxarlo en sesión
							$destino = $this->obtener_destino();
							
							//Redirección
							if ($tipo == 1)	{
								redirect("forma_pago/registrar/amex", "location", 303);	//se puede invocar pasando el consecutivo como parámetro
								exit();
							} else {
								//$this->listar("Tarjeta registrada correctamente");
								redirect($destino, "location", 303);
								exit();
							}
						} else {
							$this->listar("Hubo un error en el registro en el sistema", FALSE);
							//echo "<br/>Hubo un error en el registro en CMS";
						}
					} else {
						$this->listar("Hubo un error en el registro en el sistema para el cobro", FALSE);
						//echo "Hubo un error en el registro en CCTC";
					}
				}	//ya registrada	
			} else {
				//no se quiere guardar en BD
				$form_values['tc']['id_TCSi'] = 0;		//consec. es cero para las ediciones posibles
				
				//para la session
				$tarjeta = array('tc' => $form_values['tc'], $form_values['amex']);				
				
				//Verificar el flujo() => cargar o no en session y redireccionar
				$this->cargar_en_session($tarjeta);
				
				//Para calcular destino siguiente y actualizxarlo en sesión
				$destino = $this->obtener_destino();
				
				if ($tipo == 1)	{
					redirect("forma_pago/registrar/amex", 'location', 303);	//se puede invocar pasando el consecutivo como parámetro
					exit(); 
				} else {
					//$this->listar("Información de la tarjeta capturada correctamente");
					redirect($destino, "location", 303);
					exit();
				}
				//redirect('direccion_envio');
			}
		} else {	
			//Si hubo errores => vuelve a mostrar la info.
			//$this->cargar_vista('', 'forma_pago' , $data);
			$this->registrar("tc");	
		}
	}
	
	private function registrar_amex($id_cliente) {
		$data['title'] = $this->title;
		$data['subtitle'] = "Ingresa o edita tu direcci&oacute;n de tarjeta AMEX";
		
		if ($this->session->userdata('tarjeta')) {
			$tarjeta = $this->session->userdata('tarjeta');
			//echo "tarjeta: ". var_dump($tarjeta);
			
			if (is_array($tarjeta)) {
				//para cuando no se guarda en BD, se toma de session
				$tc = (object)$tarjeta['tc'];
			} else {
				//se recupera el consecutivo
				$consecutivo = $tarjeta;
				$tc = $this->forma_pago_model->detalle_tarjeta($consecutivo, $id_cliente);
			}
		
			$form_values = array();		
			$form_values = $this->get_datos_tarjeta();
			//var_dump($this->reg_errores);
			if (empty($this->reg_errores)) {
				$form_values['tc'] = (array)$tc;
				$form_values['amex']['id_TCSi'] = $tc->id_TCSi;
				$form_values['amex']['id_clienteIn'] = $id_cliente;
				$form_values['amex']['nombre_titularVc'] = $tc->nombre_titularVc;
				$form_values['amex']['apellidoP_titularVc'] = $tc->apellidoP_titularVc;
				$form_values['amex']['apellidoM_titularVc'] = $tc->apellidoM_titularVc;
				
				//$tarjeta = array('tc' => $form_values['tc'], 'amex' => $form_values['amex']);				
				
				//var_dump($tarjeta);
				//var_dump($form_values);
				//exit();
				
				if (isset($form_values['tc']['id_estatusSi'])) {	//Se guardará en la BD, actualizando la tarjeta que se registró
					//if ($this->editar_tarjeta_CCTC($form_values['tc'], $form_values['amex'])) {	//Se registró exitosamente! en CCTC";
					if ($this->editar_tarjeta_interfase_CCTC($form_values['tc'], $form_values['amex'])) {	//Se registró exitosamente! en CCTC";
						//Para calcular destino siguiente y actualizxarlo en sesión
						$destino = $this->obtener_destino();
						
						$this->listar("Tarjeta registrada correctamente");
						//Ya debe estar en sesión la tarjeta...
												
					} else {
						$this->listar("Hubo un error en el registro en el sistema", FALSE);
						//echo "Hubo un error en el registro en CCTC";
					}						
				} else {
					//Poner en session la TC
					$tarjeta = array('tc' => $form_values['tc'], 'amex' => $form_values['amex']);
					//si no se guardará la tc, almacenar la info para la venta 
					$this->cargar_en_session($tarjeta);
					
					//Para calcular destino siguiente y actualizxarlo en sesión
					$destino = $this->obtener_destino();
					
					//$this->listar("Tarjeta registrada correctamente");
					redirect($destino, "location", 303);
				}
			} else {	//Si hubo errores
				//vuelve a mostrar la info.
				//$data['reg_errores'] = $this->reg_errores;
				//$this->cargar_vista('', 'forma_pago' , $data);
				$this->registrar("amex");	
			}
		} else {
			$this->listar("Información de la tarjeta capturada correctamente", FALSE);
		}
	}
	
	/**
	 * Méodo para administrar la edición de la tarjeta
	 */
	public function editar($tipo = "tc", $consecutivo = 0, $back='')	//el consecutivo de la tarjeta
	{
		//exit();
		//echo "tipo: ".$tipo . " con: ".$consecutivo;
		//La edición de la info. en session no trae un consecutivo => 0
		$id_cliente = $this->id_cliente;
		
		$script_file = "<script type='text/javascript' src='". base_url() ."js/forma_pago.js'></script>";
		$data['script'] = $script_file;
		
		$data['title'] = $this->title;
		$data['subtitle'] = 'Editar tarjeta guardada';
		
		$data['vista_detalle'] = $tipo;		//selección de la vista para mostrar
		
		//Recuperación de la información para el despliegue 
		if (!$consecutivo && $this->session->userdata("tarjeta")) {		//información del objeto tomado de la session
			
			$tarjeta_en_sesion = $this->session->userdata("tarjeta");
			
			//información que se mostrará
			if ($tipo == 'tc') {
				//recupera la info. tc
				$tarjeta_tc = null;
				
				//creación del objeto para el despliegue del formulario de edición
				foreach ($tarjeta_en_sesion['tc'] as $key => $value) {
					$tarjeta_tc->$key = $value;
				}
				
				$tarjeta_tc->id_TCSi = 0;	//el id_TCSi (consecutivo debe ser 0 si está en session)
				
				//$detalle_tarjeta = $tarjeta_tc;
				$data['tarjeta_tc'] = $tarjeta_tc;
			} else if ($tipo == 'amex') {
				//recupera la info de amex
				$tarjeta_amex = null;
				
				/*echo "</pre>";
				var_dump($tarjeta_en_sesion);
				echo "</pre>";
				exit();*/
				//creación del objeto para el despliegue del formulario de edición
				if (!empty($tarjeta_en_sesion['amex'])) {
					foreach ($tarjeta_en_sesion['amex'] as $key => $value) {
						$tarjeta_amex->$key = $value;
					}
				}
				$tarjeta_amex->id_TCSi = 0;	//el id_TCSi (consecutivo debe ser 0 si está en session)
				
				$data['tarjeta_amex'] = $tarjeta_amex;
				//lista paises
				$lista_paises_amex = $this->forma_pago_model->listar_paises_amex();
				$data['lista_paises_amex'] = $lista_paises_amex;
			}
		} else {
			//recuperar la información local de la tc, utilizando el consecutivo
			if ($tipo == 'tc') { 
				//$detalle_tarjeta = $this->forma_pago_model->detalle_tarjeta($consecutivo, $id_cliente);
				$data['tarjeta_tc'] = $this->forma_pago_model->detalle_tarjeta($consecutivo, $id_cliente);
				//var_dump($data['tarjeta_tc']);
			} else if ($tipo == 'amex') {
				//$data['tarjeta_amex'] = $this->detalle_tarjeta_CCTC($id_cliente, $consecutivo);
				$data['tarjeta_amex'] = $this->obtener_detalle_interfase_CCTC($id_cliente, $consecutivo);
				//lista paises
				$lista_paises_amex = $this->forma_pago_model->listar_paises_amex();
				$data['lista_paises_amex'] = $lista_paises_amex;
				$data['subtitle'] = "Ingresa o edita tu direcci&oacute;n de tarjeta AMEX";
				
				//Si no hay info de amex en cctc
				if ($data['tarjeta_amex']->consecutivo_cmsSi == 0)
					$data['tarjeta_amex']->consecutivo_cmsSi = $consecutivo;
			}
		}
		
		if ($_POST && empty($this->reg_errores)) {
			//Se actualizará la tarjeta en BD
			if ($tipo == 'tc') {
				$this->editar_tc($consecutivo);
			} else if ($tipo == 'amex') {
				$this->editar_amex($consecutivo);
			}
			//else, redireccionar/pantalla de error
		} else if (!empty($this->reg_errores)) {
			$data['reg_errores'] = $this->reg_errores;
			$this->cargar_vista('', 'forma_pago' , $data);
		} else {	//If POST
			//echo "editar - con: ".$consecutivo . " tipo: " . $tipo;
			//exit();
			$this->cargar_vista('', 'forma_pago' , $data);
		}
	}
	
	private function editar_tc($consecutivo = 0) {
		$id_cliente = $this->id_cliente;
		
		//Revisar en donde está la info
		if ($consecutivo > 0) {
			//edicion desde listado u Orden
			
			//echo "<br/>deben coincidir si es que hay info en session: ".$consecutivo . "(consec.) == (tarjeta)". $this->session->userdata('tarjeta')."<br/>";
			//exit();
			
			//el detalle de la tarjeta en BD antes de actualizar
			$detalle_tarjeta = $this->forma_pago_model->detalle_tarjeta($consecutivo, $id_cliente);
			
			//info_amex
			if ($detalle_tarjeta->id_tipo_tarjetaSi == 1 ) 
				//$detalle_amex = $this->detalle_tarjeta_CCTC($id_cliente, $consecutivo);
				$detalle_amex = $this->obtener_detalle_interfase_CCTC($id_cliente, $consecutivo);
			
			//echo print_r($detalle_tarjeta);
			//echo print_r($detalle_amex);
			//exit();
			
		} else	if ($this->session->userdata('tarjeta') && $consecutivo == 0) {	
			//tarjeta en session y no registrada en BD, viene de la Orden => $consecutivo debería ser 0
			
			$tarjeta = $this->session->userdata('tarjeta');
			
			if (is_array($tarjeta)) {
				//echo "debe ser cero: ".$consecutivo;
				//exit();
				
				//para cuando no se guarda en BD, se toma de session
				$detalle_tarjeta = (object)$tarjeta['tc'];
				
				if ($detalle_tarjeta->id_tipo_tarjetaSi == 1 ) 
					$detalle_amex = (object)$tarjeta['amex'];
				//$consecutivo = 0;
			}
		}

		//array para la nueva información
		$nueva_info = array();
		$nueva_info = $this->get_datos_tarjeta();	//datos generales
		
		$redirect = $nueva_info['redirect'];
		/*
		var_dump($_POST);
		echo "<br/>";
		var_dump($nueva_info);
		
		echo "<br/>redirect: <br/>";
		echo $redirect;
		exit();
		*/
		$tarjeta = array();
		
		//errores
		$data['reg_errores'] = $this->reg_errores;
		
		if (empty($data['reg_errores'])) {	//si no hubo errores
			//preparar la petición al WS, campos comunes
			$nueva_info['tc']['id_clienteIn'] = $id_cliente;
			$nueva_info['tc']['id_TCSi'] = $consecutivo;
			$nueva_info['tc']['terminacion_tarjetaVc'] = $detalle_tarjeta->terminacion_tarjetaVc;
			$nueva_info['tc']['descripcionVc'] = $detalle_tarjeta->descripcionVc;
			$nueva_info['tc']['id_tipo_tarjetaSi'] = $detalle_tarjeta->id_tipo_tarjetaSi;
			
			if ($detalle_tarjeta->id_tipo_tarjetaSi == 1 ) {	//es AMEX y hay información
				//var_dump($detalle_amex);
				
				$nueva_info['amex']['id_clienteIn'] = $id_cliente;
				$nueva_info['amex']['id_TCSi'] = $detalle_tarjeta->id_TCSi;
				$nueva_info['amex']['nombre_titularVc'] = $nueva_info['tc']['nombre_titularVc'];
				$nueva_info['amex']['apellidoP_titularVc'] = $nueva_info['tc']['apellidoP_titularVc'];
				$nueva_info['amex']['apellidoM_titularVc'] = $nueva_info['tc']['apellidoM_titularVc'];
				//var_dump($detalle_amex);	//$detalle_amex trae al menos: consecutivo_cmsSi y id_clienteIn
				
				$nueva_info['amex']['pais'] = isset($detalle_amex->pais) ? $detalle_amex->pais : NULL;
				$nueva_info['amex']['codigo_postal'] = isset($detalle_amex->codigo_postal) ? $detalle_amex->codigo_postal : NULL;
				$nueva_info['amex']['calle'] = isset($detalle_amex->calle) ? $detalle_amex->calle : NULL;
				$nueva_info['amex']['ciudad'] = isset($detalle_amex->ciudad) ? $detalle_amex->ciudad : NULL;
				$nueva_info['amex']['estado'] = isset($detalle_amex->estado) ? $detalle_amex->estado : NULL;
				$nueva_info['amex']['mail'] = isset($detalle_amex->mail) ? $detalle_amex->mail : $this->session->userdata('email');
				$nueva_info['amex']['telefono'] = isset($detalle_amex->telefono) ? $detalle_amex->telefono : NULL;
			} else {
				$nueva_info['amex'] = NULL;
			}
			
			if (!$consecutivo) {
				//tarjeta en session en caso de ser necesario
				$tarjeta = array('tc' => $nueva_info['tc'], 'amex' => $nueva_info['amex']);
				$this->cargar_en_session($tarjeta);
				
				//Para el destino siguiente
				//$this->pago_express->actualizar_forma_pago($nueva_info['tc']['id_TCSi']);
				$destino = $this->obtener_destino();
				
				$msg_actualizacion = "Información actualizada correctamente";
				$data['msg_actualizacion'] = $msg_actualizacion;
				
				if ($detalle_tarjeta->id_tipo_tarjetaSi == 1)	{
					//si es AMEX
					if (!$redirect) {	//si no requiere redirección
						$this->session->set_userdata('destino', 'forma_pago/');
					}
					
					//redirect('forma_pago/editar/amex/'.$consecutivo, 'location', 303);
					$url = site_url('forma_pago/editar/amex/'.$consecutivo);
					header("HTTP/1.1 303 See Other");
					header("Location: $url", TRUE, 303);
				} else {
					//$this->listar($msg_actualizacion, $redirect);
					if ($redirect) {
						//redirect($destino, "location", 303);
						$url = site_url($destino);
						header("HTTP/1.1 303 See Other");
						header("Location: $url", TRUE, 303);
					} else {
						//redirect("forma_pago", 'location', 303);
						$url = site_url('forma_pago');
						header("HTTP/1.1 303 See Other");
						header("Location: $url", TRUE, 303);
					}
				}
			} else {
				
				//actualizar en CCTC, si el consecutivo es distinto de 0				
				//if ($this->editar_tarjeta_CCTC($nueva_info['tc'], $nueva_info['amex'])) {
				if ($this->editar_tarjeta_interfase_CCTC($nueva_info['tc'], $nueva_info['amex'])) {
					//actualizar predeterminado
					if (isset($nueva_info['predeterminar'])) {
						$this->forma_pago_model->quitar_predeterminado($id_cliente);
					} else {
						$nueva_info['tc']['id_estatusSi'] = 1;
					}
					
					//ahora para registrar cambios localmente, siempre se manda la info de $nueva_info['tc']
					$msg_actualizacion = $this->forma_pago_model->actualiza_tarjeta($consecutivo, $id_cliente, $nueva_info['tc']);
										
					//Cargar la tarjeta en la sesión y calcular el flujo
					$this->cargar_en_session($consecutivo);
					
					//Para calcular destino siguiente y actualizxarlo en sesión
					$destino = $this->obtener_destino();
					
					$data['msg_actualizacion'] = $msg_actualizacion;
					
					$_POST = array();
					
					if ($detalle_tarjeta->id_tipo_tarjetaSi == 1)	{
						if (!$redirect) {	//si no requiere redirección
							$this->session->set_userdata('destino', 'forma_pago/');
						}
						//redirect('forma_pago/editar/amex/'.$consecutivo, "location", 303);
						$url = site_url('forma_pago/editar/amex/'.$consecutivo);
						header("HTTP/1.1 303 See Other");
						header("Location: $url", TRUE, 303);
					} else {
						//$this->listar($msg_actualizacion, $redirect);
						if ($redirect) {
							//redirect($destino, "location", 303);
							$url = site_url($destino);
							header("HTTP/1.1 303 See Other");
							header("Location: $url", TRUE, 303);
						} else {
							//redirect("forma_pago", 'location', 303);
							$url = site_url('forma_pago');
							header("HTTP/1.1 303 See Other");
							header("Location: $url", TRUE, 303);
						}
					}
				} else {
					$data['msg_actualizacion'] = "Error de actualización en el sistema para el cobro";
					//echo "Error de actualización hacia CCTC.<br/>";	//redirect
					//$this->cargar_vista('', 'forma_pago' , $data);
					$this->listar($data['msg_actualizacion'], FALSE);
					exit;
				}
			}
		} else {	//sí hubo errores
			//$data['msg_actualizacion'] = "Campos incorrectos";
			//$this->cargar_vista('', 'forma_pago' , $data);
			$this->editar("tc", $consecutivo);
		}
	}
	
	private function editar_amex($consecutivo = 0) {
		//exit();
		$data['title'] = $this->title;
		$data['subtitle'] = ucfirst('Edicion direccion');
		
		$id_cliente = $this->id_cliente;
		$tarjeta = array();
		
		//Revisar en donde está la info
		if ($consecutivo > 0) {
			//edicion desde listado u Orden
			//echo "deben coincidir si es que hay info en session: ".$consecutivo . "(consec.) == (tarjeta)". $this->session->userdata('tarjeta')."<br/>";
			//exit();
			
			//el detalle de la tarjeta en BD antes de actualizar
			$detalle_tarjeta = $this->forma_pago_model->detalle_tarjeta($consecutivo, $id_cliente);
			
			//info_amex
			if ($detalle_tarjeta->id_tipo_tarjetaSi == 1 ) 
				//$detalle_amex = $this->detalle_tarjeta_CCTC($id_cliente, $consecutivo);
				$detalle_amex = $this->obtener_detalle_interfase_CCTC($id_cliente, $consecutivo);
			
		} else	if ($this->session->userdata('tarjeta') && $consecutivo == 0) {	
			//tarjeta en session y no registrada en BD, viene de la Orden => $consecutivo debería ser 0
			
			$tarjeta = $this->session->userdata('tarjeta');
			
			if (is_array($tarjeta)) {
				//echo "debe ser cero: ".$consecutivo;
				//exit();
				
				//para cuando no se guarda en BD, se toma de session
				$detalle_tarjeta = (object)$tarjeta['tc'];
				
				if ($detalle_tarjeta->id_tipo_tarjetaSi == 1 ) 
					$detalle_amex = (object)$tarjeta['amex'];
				//$consecutivo = 0;
			}
			 /*else {
			 	echo "deben coincidir: ".$consecutivo . "(consec.) == (tarjeta)". $tarjeta;
				 //exit();
				 $detalle_tarjeta = $this->forma_pago_model->detalle_tarjeta($consecutivo, $id_cliente);
			 }*/
		}
		
		$nueva_info = array();
		$nueva_info = $this->get_datos_tarjeta();	//datos generales
	
		
		//errores
		$data['reg_errores'] = $this->reg_errores;

		if (empty($data['reg_errores'])) {	//si no hubo errores
			//preparar la petición al WS, campos comunes
			$nueva_info['tc'] = (array)$detalle_tarjeta;
			
			$nueva_info['amex']['id_clienteIn'] = $id_cliente;
			$nueva_info['amex']['id_TCSi'] = $detalle_tarjeta->id_TCSi;
			$nueva_info['amex']['nombre_titularVc'] = $detalle_tarjeta->nombre_titularVc;
			$nueva_info['amex']['apellidoP_titularVc'] = $detalle_tarjeta->apellidoP_titularVc;
			$nueva_info['amex']['apellidoM_titularVc'] = $detalle_tarjeta->apellidoM_titularVc;
			
			
			if (!$consecutivo) {
				/*echo "en session";
				var_dump($nueva_info);
				exit();*/
				
				$tarjeta = array('tc' => $nueva_info['tc'], 'amex' => $nueva_info['amex']);
				$this->cargar_en_session($tarjeta);
				
				$msg_actualizacion = "Información actualizada";
				$data['msg_actualizacion'] = $msg_actualizacion;
				
				//se tiene que tomar de la session, por si ya se calculó en la edición de la TC
				$destino = $this->session->userdata('destino');
				//$this->listar($msg_actualizacion);
				redirect($destino, 'location', 303);
				
			} else {					
				//actualizar SÓLO en CCTC, si el consecutivo es distinto de 0
				//if ($this->editar_tarjeta_CCTC($nueva_info['tc'], $nueva_info['amex'])) {
				if ($this->editar_tarjeta_interfase_CCTC($nueva_info['tc'], $nueva_info['amex'])) {
					//verificar Flujo y cargar en session si es necesario en la misma validación
					//////////*********************
					
					$this->cargar_en_session($consecutivo);
					
					$msg_actualizacion = "Información actualizada en el sistema";
					$data['msg_actualizacion'] = $msg_actualizacion;
					
					//se tiene que tomar de la session, por si ya se calculó en la edición de la TC
					$destino = $this->session->userdata('destino');	
					//$this->listar($msg_actualizacion);
					redirect($destino, 'location', 302);
				} else {
					$data['msg_actualizacion'] = "Error de actualización de la dirección en en el servidor";
					//echo "Error de actualización hacia CCTC.<br/>";	//redirect
					$this->listar($data['msg_actualizacion'], FALSE);
					//$this->cargar_vista('', 'forma_pago' , $data);					
				}
			}
		} else {	//si hubo errores
			//$data['msg_actualizacion'] = "Campos incorrectos";
			//$this->cargar_vista('', 'forma_pago' , $data);
			$this->editar("amex", $consecutivo);
		}
		
	}
	
	public function eliminar($consecutivo = '')
	{
		$id_cliente = $this->id_cliente;
		$data['title'] = $this->title;
		$data['subtitle'] = ucfirst('Eliminar Forma de Pago');
		
		//exit();
		//if ($this->eliminar_tarjeta_CCTC($id_cliente, $consecutivo)) {
		if ($this->eliminar_tarjeta_interfase_CCTC($id_cliente, $consecutivo)) {			
		//if (1) {
			//echo "Eliminado correctamente de CCTC";
			//eliminar lógicamente en la bd local
			$msg_eliminacion =
				$this->forma_pago_model->eliminar_tarjeta($id_cliente, $consecutivo);
		} else {
			//echo "no se pudo eliminar correctamente de CCTC";
			$msg_eliminacion = "No se pudo eliminar correctamente la tarjeta del sistema de cobro";
		}
		/*Pendiente el Redirect*/
		//echo "<br/>se eliminó la tarjeta $consecutivo del cliente $id_cliente<br/>";
		//echo $data['msg_eliminacion´];
		
		if ($numero_t = $this->session->userdata("tarjeta")) {
			if ((int)$numero_t == (int)$consecutivo) {
				$this->session->unset_userdata("tarjeta");
			}
		}
		
		//cargar la lista
		$this->listar($msg_eliminacion, FALSE);
		//$this->cargar_vista('', 'forma_pago' , $data);
	}
	
	/**
	 * Eliminar tarjeta del cliente, dejándola inactiva tanto en CCTC como en la BD de ecommerce.
	 */
	private function eliminar_tarjeta_interfase_CCTC($id_cliente = 0, $consecutivo = 0) {
		if (isset($id_cliente, $consecutivo)) {
			// Metemos todos los parametros (Objetos) necesarios a una clase dinámica llamada paramátros //
			$parametros = new stdClass;
			$parametros->id_cliente = $id_cliente;
			$parametros->consecutivo = $consecutivo;
			
			// Hacemos un encode de los objetos para poderlos pasar por POST ...
			$param = json_encode($parametros);
			
			/*
			echo "<pre>";
			print_r($parametros);
			echo "</pre>". "ecoded:" ;
			echo $param."<br/>";
			exit;
			*/
			
			// Inicializamos el CURL / SI no funciona se puede habilitar en el php.ini //
			$c = curl_init();
			// CURL de la URL donde se haran las peticiones //
			curl_setopt($c, CURLOPT_URL, 'http://dev.interfase.mx/interfase.php');
			//curl_setopt($c, CURLOPT_URL, 'http://10.43.29.196/interface_cctc/solicitar_post.php');
			// Se enviaran los datos por POST //
			curl_setopt($c, CURLOPT_POST, true);
			// Que nos envie el resultado del JSON //
			curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
			// Enviamos los parametros POST //
			curl_setopt($c, CURLOPT_POSTFIELDS, 'accion=EliminarTarjeta&token=123456&parametros='.$param);
			// Ejecutamos y recibimos el JSON //
			$resultado = curl_exec($c);
			// Cerramos el CURL //
			curl_close($c);
			/*
			echo "Resultado<pre>";
			print_r(json_decode($resultado));
			echo "</pre>";
			exit;
			*/
			return json_decode($resultado);
		}
	}

	/**
	 * Eliminar tarjeta del cliente, dejándola inactiva tanto en CCTC como en la BD de ecommerce.
	 */
	private function eliminar_tarjeta_CCTC($id_cliente = 0, $consecutivo = 0)
	{
		try {  
			$cliente = new SoapClient("https://cctc.gee.com.mx/ServicioWebCCTC/ws_cms_cctc.asmx?WSDL");
				
			$parameter = array('id_clienteNu' => $id_cliente, 'consecutivo_cmsSi' => $consecutivo);
			
			$obj_result = $cliente->EliminarTC($parameter);
			$simple_result = $obj_result->EliminarTCResult;
			
			//print($simple_result);
			
			return $simple_result;
			
		} catch (SoapFault $exception) {
			//echo "//tarjeta No Eliminada";
			//echo $exception;  
			//echo '<br/>error: <br/>'.$exception->getMessage();
			//exit();
			return false;
		}
	}

	/**
	 * Obtiene el detalle de la tarjeta Amex desde CCTC.
	 * Siempre será la información de AMEX sólamente.
	 */
	private function obtener_detalle_interfase_CCTC($id_cliente = 0, $consecutivo = 0) {
		if (isset($id_cliente, $consecutivo)) {
			// Metemos todos los parametros (Objetos) necesarios a una clase dinámica llamada paramátros //
			$parametros = new stdClass;
			$parametros->id_cliente = $id_cliente;
			$parametros->consecutivo = $consecutivo;
			
			// Hacemos un encode de los objetos para poderlos pasar por POST ...
			$param = json_encode($parametros);
			
			// Inicializamos el CURL / SI no funciona se puede habilitar en el php.ini //
			$c = curl_init();
			// CURL de la URL donde se haran las peticiones //
			curl_setopt($c, CURLOPT_URL, 'http://dev.interfase.mx/interfase.php');
			//curl_setopt($c, CURLOPT_URL, 'http://10.43.29.196/interface_cctc/solicitar_post.php');
			// Se enviaran los datos por POST //
			curl_setopt($c, CURLOPT_POST, true);
			// Que nos envie el resultado del JSON //
			curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
			// Enviamos los parametros POST //
			curl_setopt($c, CURLOPT_POSTFIELDS, 'accion=ObtenerDetalleAmex&token=123456&parametros='.$param);
			// Ejecutamos y recibimos el JSON //
			$resultado = curl_exec($c);
			// Cerramos el CURL //
			curl_close($c);
			/*
			echo "Resultado<pre>";
			print_r(json_decode($resultado));
			echo "</pre>";
			exit;
			*/
			return json_decode($resultado);
		}
	}

	/**
	 * Obtiene el detalle de la tarjeta Amex desde CCTC
	 */
	private function detalle_tarjeta_CCTC($id_cliente=0, $consecutivo=0)	//siempre será la información de AMEX
	{
		//Traer la info de amex
		try {  
			$cliente = new SoapClient("https://cctc.gee.com.mx/ServicioWebCCTC/ws_cms_cctc.asmx?WSDL");
				
			$parameter = array(	'id_clienteNu' => $id_cliente, 'consecutivo_cmsSi' => $consecutivo);
			
			$obj_result = $cliente->ConsultarAmex($parameter);
			$tarjeta_amex = $obj_result->ConsultarAmexResult;	//regresa un objeto
			
			//print($simple_result);
			
			return $tarjeta_amex;
			
		} catch (SoapFault $exception) {
			//echo "detalle_tarjeta_CCTC";
			//echo $exception;  
			//echo '<br/>error: <br/>'.$exception->getMessage();
			//exit();
			return false;
		}
	}
	
	/**
	 * Actualiza la información de la tarjeta Amex en CCTC.
	 * Siempre será la información de AMEX sólamente.
	 */
	private function editar_tarjeta_interfase_CCTC($tc, $amex = null)
	{
		//mapeo de la tc
		$tc_soap = new stdClass;
		$tc_soap->id_clienteIn = $tc['id_clienteIn'];
		$tc_soap->consecutivo_cmsSi = $tc['id_TCSi'];
		$tc_soap->id_tipo_tarjeta = $tc['id_tipo_tarjetaSi'];
		$tc_soap->nombre_titular = $tc['nombre_titularVc'];
		$tc_soap->apellidoP_titular = $tc['apellidoP_titularVc'];
		$tc_soap->apellidoM_titular = $tc['apellidoM_titularVc'];
		$tc_soap->numero = $tc['terminacion_tarjetaVc'];
		$tc_soap->mes_expiracion = $tc['mes_expiracionVc'];
		$tc_soap->anio_expiracion = $tc['anio_expiracionVc'];
		$tc_soap->renovacion_automatica = 1;
		
		//mapeo Amex
		if (isset($amex)) {
			$amex_soap = new stdClass;
			$amex_soap->id_clienteIn = $amex['id_clienteIn'];
			$amex_soap->consecutivo_cmsSi = $amex['id_TCSi'];
			$amex_soap->nombre =$amex['nombre_titularVc'];
			$amex_soap->apellido_paterno = $amex['apellidoP_titularVc'];
			$amex_soap->apellido_materno = $amex['apellidoM_titularVc'];
			$amex_soap->pais = $amex['pais'];
			$amex_soap->codigo_postal = $amex['codigo_postal'];
			$amex_soap->calle = $amex['calle'];
			$amex_soap->ciudad = $amex['ciudad'];
			$amex_soap->estado = $amex['estado'];
			$amex_soap->mail = $amex['mail'];
			$amex_soap->telefono = $amex['telefono'];
			
		} else {
			$amex_soap = null;
		}
		
		########## petición a la Interfase
		// Metemos todos los parametros (Objetos) necesarios a una clase dinámica llamada paramátros //
		$parametros = new stdClass;
		$parametros->tc_soap = $tc_soap;
		$parametros->amex_soap = $amex_soap;
		
		// Hacemos un encode de los objetos para poderlos pasar por POST ...
		$param = json_encode($parametros);
		
		/*
		echo "<pre>";
		print_r($parametros);
		echo "</pre>". "ecoded:" ;
		echo $param."<br/>";
		exit;
		
		$p = json_decode($param);
		$objetos = $this->ArrayToObject($p);
		echo "<pre>";
		print_r($objetos);
		echo "</pre>";
		*/
				
		// Inicializamos el CURL / SI no funciona se puede habilitar en el php.ini //
		$c = curl_init();
		// CURL de la URL donde se haran las peticiones //
		curl_setopt($c, CURLOPT_URL, 'http://dev.interfase.mx/interfase.php');
		//curl_setopt($c, CURLOPT_URL, 'http://10.43.29.196/interface_cctc/solicitar_post.php');
		// Se enviaran los datos por POST //
		curl_setopt($c, CURLOPT_POST, true);
		// Que nos envie el resultado del JSON //
		curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
		// Enviamos los parametros POST //
		curl_setopt($c, CURLOPT_POSTFIELDS, 'accion=ActualizarAmex&token=123456&parametros='.$param);
		// Ejecutamos y recibimos el JSON //
		$resultado = curl_exec($c);
		// Cerramos el CURL //
		curl_close($c);
		/*
		echo "Resultado<pre>";
		print_r(json_decode($resultado));
		echo "</pre>";
		exit;
		*/
		return json_decode($resultado);
	}
 
	/**
	 * Actualiza la información de la tarjeta Amex en CCTC
	 */
	private function editar_tarjeta_CCTC($tc, $amex = null)
	{
		//var_dump($tc);
		//var_dump($amex);
		//exit();
		$resultado = FALSE;
		//mapeo de la tc
		$tc_soap = new Tc(
			$tc['id_clienteIn'],
			$tc['id_TCSi'],
			$tc['id_tipo_tarjetaSi'],
			$tc['nombre_titularVc'],
			$tc['apellidoP_titularVc'],
			$tc['apellidoM_titularVc'],
			$tc['terminacion_tarjetaVc'],
			$tc['mes_expiracionVc'],
			$tc['anio_expiracionVc']
			//'renovacion_automatica' => $tc[']//Mandar true para que se guarde
		);
		
		//mapeo Amex
		if (isset($amex)) {
			$amex_soap = new Amex(
				$amex['id_clienteIn'],
				$amex['id_TCSi'],
				$amex['nombre_titularVc'],
				$amex['apellidoP_titularVc'],
				$amex['apellidoM_titularVc'],
				$amex['pais'],
				$amex['codigo_postal'],
				$amex['calle'],
				$amex['ciudad'],
				$amex['estado'],
				$amex['mail'],
				$amex['telefono']
			);
		} else {
			$amex_soap = null;
		}
		//var_dump($tc_soap);
		//var_dump($amex_soap);
		//echo (isset($amex));
		//exit();
		
		try {  
			$cliente = new SoapClient("https://cctc.gee.com.mx/ServicioWebCCTC/ws_cms_cctc.asmx?WSDL");
			
			$parameter = array('informacion_tarjeta' => $tc_soap, 'informacion_amex' => $amex_soap);
			
			$obj_result = $cliente->EditarTC($parameter);
			$simple_result = $obj_result->EditarTCResult;
			
			//print($simple_result);
			
			return $simple_result;
			
		} catch (SoapFault $exception) {
			//echo "error editar_TC";
			//echo $exception;  
			//echo '<br/>error: <br/>'.$exception->getMessage();
			//exit();
			return false;
		}
	}

	/**
	 * Registra una tarjeta en CCTC
	 * En principio se usa para registrar una tarjeta en forma genérica,
	 * es decir, con o sin dirección Amex.
	 */
	private function registrar_tarjeta_interfase_CCTC($tc, $amex = null)
	{
		//mapeo de la tc
		$tc_soap = new stdClass;
		$tc_soap->id_clienteIn = $tc['id_clienteIn'];
		$tc_soap->consecutivo_cmsSi = $tc['id_TCSi'];
		$tc_soap->id_tipo_tarjeta = $tc['id_tipo_tarjetaSi'];
		$tc_soap->nombre_titular = $tc['nombre_titularVc'];
		$tc_soap->apellidoP_titular = $tc['apellidoP_titularVc'];
		$tc_soap->apellidoM_titular = $tc['apellidoM_titularVc'];
		$tc_soap->numero = $tc['terminacion_tarjetaVc'];
		$tc_soap->mes_expiracion = $tc['mes_expiracionVc'];
		$tc_soap->anio_expiracion = $tc['anio_expiracionVc'];
		$tc_soap->renovacion_automatica = 1;
		
		//mapeo Amex
		if (isset($amex)) {
			$amex_soap = new stdClass;
			$amex_soap->id_clienteIn = $amex['id_clienteIn'];
			$amex_soap->consecutivo_cmsSi = $amex['id_TCSi'];
			$amex_soap->nombre =$amex['nombre_titularVc'];
			$amex_soap->apellido_paterno = $amex['apellidoP_titularVc'];
			$amex_soap->apellido_materno = $amex['apellidoM_titularVc'];
			$amex_soap->pais = $amex['pais'];
			$amex_soap->codigo_postal = $amex['codigo_postal'];
			$amex_soap->calle = $amex['calle'];
			$amex_soap->ciudad = $amex['ciudad'];
			$amex_soap->estado = $amex['estado'];
			$amex_soap->mail = $amex['mail'];
			$amex_soap->telefono = $amex['telefono'];
			
		} else {
			$amex_soap = null;
		}
		
		########## petición a la Interfase
		// Metemos todos los parametros (Objetos) necesarios a una clase dinámica llamada paramátros //
		$parametros = new stdClass;
		$parametros->tc_soap = $tc_soap;
		$parametros->amex_soap = $amex_soap;
		
		// Hacemos un encode de los objetos para poderlos pasar por POST ...
		$param = json_encode($parametros);
		
		/*
		echo "<pre>";
		print_r($parametros);
		echo "</pre>". "ecoded:" ;
		echo $param."<br/>";
		//exit;
		
		$p = json_decode($param);
		$objetos = $this->ArrayToObject($p);
		echo "<pre>";
		print_r($objetos);
		echo "</pre>";
		exit;
		*/
		// Inicializamos el CURL / SI no funciona se puede habilitar en el php.ini //
		$c = curl_init();
		// CURL de la URL donde se haran las peticiones //
		curl_setopt($c, CURLOPT_URL, 'http://dev.interfase.mx/interfase.php');
		//curl_setopt($c, CURLOPT_URL, 'http://10.43.29.196/interface_cctc/solicitar_post.php');
		// Se enviaran los datos por POST //
		curl_setopt($c, CURLOPT_POST, true);
		// Que nos envie el resultado del JSON //
		curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
		// Enviamos los parametros POST //
		curl_setopt($c, CURLOPT_POSTFIELDS, 'accion=RegistrarTarjeta&token=123456&parametros='.$param);
		// Ejecutamos y recibimos el JSON //
		$resultado = curl_exec($c);
		// Cerramos el CURL //
		curl_close($c);
		/*
		echo "Resultado<pre>";
		print_r(json_decode($resultado));
		echo "</pre>";
		exit;
		*/
		return json_decode($resultado);
	}
 
	/**
	 * Registra una tarjeta en CCTC
	 */
	private function registrar_tarjeta_CCTC($tc, $amex = null) 
	{
		$resultado = FALSE;
		//mapeo de la tc
		$tc_soap = new Tc(
			$tc['id_clienteIn'],
			$tc['id_TCSi'],
			$tc['id_tipo_tarjetaSi'],
			$tc['nombre_titularVc'],
			$tc['apellidoP_titularVc'],
			$tc['apellidoM_titularVc'],
			$tc['terminacion_tarjetaVc'],
			$tc['mes_expiracionVc'],
			$tc['anio_expiracionVc']
			//'renovacion_automatica' => $tc[']//Mandar true para que se guarde
		);
		
		//mapeo Amex
		if (isset($amex)) {
			$amex_soap = new Amex(
				$amex['id_clienteIn'],
				$amex['id_TCSi'],
				$amex['nombre_titularVc'],
				$amex['apellidoP_titularVc'],
				$amex['apellidoM_titularVc'],
				$amex['pais'], $amex['codigo_postal'],
				$amex['calle'], $amex['ciudad'],
				$amex['estado'], $amex['mail'],
				$amex['telefono']
			);
		} else {
			$amex_soap = null;
		}
		
		try {  
			$cliente = new SoapClient("https://cctc.gee.com.mx/ServicioWebCCTC/ws_cms_cctc.asmx?WSDL");
				
			$parameter = array(	'informacion_tarjeta' => $tc_soap, 'informacion_amex' => $amex_soap);
			
			$obj_result = $cliente->InsertarTC($parameter);
			$simple_result = $obj_result->InsertarTCResult;
			
			//print($simple_result);
			
			return $simple_result;
			
		} catch (SoapFault $exception) {
//			echo "registrar_tarjeta_CCTC";
			//errores en desarrollo
			//echo $exception;  
			//echo '<br/>error: <br/>'.$exception->getMessage();
			//exit();
			return false;
		}
	}
	/*
	 * Consulta del catálogo de tarjetas de Banco de CCTC
	 * */
	private function listar_tipos_tarjeta_WS() 
	{	
		try {
			//URL del WS debe estar en archivo protegido  
			$cliente = new SoapClient("https://cctc.gee.com.mx/ServicioWebCCTC/ws_cms_cctc.asmx?WSDL");	
			
			//Recupera la lista de bancos
			$ObtenerBancosResponse = $cliente->ObtenerBancos();		//Repuesta inicial del WS
			$ObtenerBancosResult = $ObtenerBancosResponse->ObtenerBancosResult;
			$InformacionBancoArray = $ObtenerBancosResult->InformacionBanco;
			//print var_dump($InformacionBancoArray);	//arreglo de objetos
				
			return $InformacionBancoArray;
			
		} catch (Exception $e) {
			//echo "No se pudo recuperar el catálogo de bancos.<br/>";
			//echo $e->getMessage();
			//exit();
			return FALSE;
		}
	}
	
	/**
	 * Verifica si existe o no alguna tarjeta predeterminada para pago express del cliente.
	 * Retuen True/False
	 */
	private function existe_predetereminada($id_cliente)
	{
		return $this->forma_pago_model->existe_predetereminada($id_cliente);
	}
	
	/**
	 * Se enecarga de definir la navegación de la plataforma de acuerdo a la actualización de las formas de pago
	 */
	private function obtener_destino()
	{
		//Inicializar el destino con un valor por defecto.
		$destino = $this->session->userdata('destino') ? $this->session->userdata('destino') : "forma_pago";
		
		if ($this->session->userdata('tarjeta') || $this->session->userdata('deposito')) {	//tiene forma de pago
			//actualizar valores en sesión
			if ($this->session->userdata('requiere_envio')) {
				//Si hay dirección de envío seleccionada
				if ($this->session->userdata('dir_envio')) {
					//Si hay dirección de facturación Y razón social
					if ($this->session->userdata('direccion_f') && $this->session->userdata('razon_social')) {
						$destino = "orden_compra";
					} else {	//NO dir. facturación
						if ($this->forma_pago_model->existe_compra($this->id_cliente)) {	//compra
							$destino = "orden_compra";
						} else {
							if($this->session->userdata('requiere_factura')=='no'){
								$destino = "orden_compra";	
							}
							else{
								$destino = "direccion_facturacion";
							}
							
						}						
					}
				} else {
					$destino = "direccion_envio";
				}
			} else {
				//Si hay dirección de facturación Y razón social
				if ($this->session->userdata('direccion_f') && $this->session->userdata('razon_social')) {
					$destino = "orden_compra";
				} else {	//NO dir. facturación
					if ($this->forma_pago_model->existe_compra($this->id_cliente)) {	//compra
						$destino = "orden_compra";
					} else {
						if($this->session->userdata('requiere_factura')=='no'){
							$destino = "orden_compra";	
						}
						else{
							$destino = "direccion_facturacion";
						}
					}						
				} 
			}
		} else {	//no tiene forma de pago
			$destino =  "forma_pago";
		}
		
		/*
		if ($this->session->userdata('tarjeta') || $this->session->userdata('deposito')) {	//tiene forma de pago
			//actualizar valores en sesión
			if ($this->session->userdata('requiere_envio')) {
				//Si hay dirección de envío seleccionada...
				if ($this->session->userdata('dir_envio')) {
					//Si hay dirección de facturación Y razón Social
					if ($this->session->userdata('direccion_f') && $this->session->userdata('razon_social')) {
						$destino = "orden_compra";
					} else {
						$destino = "direccion_facturacion";
					}
				} else {
					$destino = "direccion_envio";
				}
			} else {
				//no requiere dirección de envío
				//Si hay dirección de facturación Y razón Social
				if ($this->session->userdata('direccion_f') && $this->session->userdata('razon_social')) {
					$destino = "orden_compra";
				} else {
					$destino = "direccion_facturacion";
				}
			}
		} else {	//no tiene forma de pago
			$destino =  "forma_pago";
		}
		*/
		//Actualizar en sesión
		$this->session->set_userdata('destino', $destino);
		
		return $destino;
	}
	
	/*
	 * Obtiene la descripción de la tarjeta para el registro
	 * */
	private function get_descripcion_tarjeta($id_tipo_tarjetaSi, $lista_tipo_tarjeta)
	{
		$descripcion = "Tarjeta no registrada";
		foreach ($lista_tipo_tarjeta as $tipo_tarjeta) {
			if ($id_tipo_tarjetaSi == $tipo_tarjeta->id_tipo_tarjeta) {
				$descripcion = $tipo_tarjeta->descripcion;
				return $descripcion;
			}
		}
		return $descripcion;
	}
	
	/*
	 * Consulta del catálogo de tarjetad de Banco local
	 * */
	private function listar_tipos_tarjeta() 
	{	
		$lista_tipo_tarjeta = $this->forma_pago_model->listar_tipos_tarjeta();
		return $lista_tipo_tarjeta;
	}
	
	private function get_datos_tarjeta()
	{
		$datos = array();
		$tipo = '';
		//echo "tipo : ". $tipo;
		//no se usa la funcion de escape '$this->db->escape()', por que en la inserción ya se incluye 
		if($_POST) {
			if (array_key_exists('sel_tipo_tarjeta', $_POST)) {
				$datos['tc']['id_tipo_tarjetaSi'] = $_POST['sel_tipo_tarjeta'];
				$tipo = $_POST['sel_tipo_tarjeta'];
			}
			
			if (array_key_exists('txt_numeroTarjeta', $_POST)) {
				if ($this->validar_tarjeta($datos['tc']['id_tipo_tarjetaSi'], trim($_POST['txt_numeroTarjeta']))) { 
					$datos['tc']['terminacion_tarjetaVc'] = trim($_POST['txt_numeroTarjeta']);	//substr($_POST['txt_numeroTarjeta'], strlen($_POST['txt_numeroTarjeta']) - 4);
				} else {
					$this->reg_errores['txt_numeroTarjeta'] = 'Por favor ingrese un numero de tarjeta v&aacute;lido';
				}
			}
			if (array_key_exists('txt_nombre', $_POST)) {
				if(preg_match('/^[A-ZáéíóúÁÉÍÓÚÑñ \'.-]{1,30}$/i', $_POST['txt_nombre'])) { 
					$datos['tc']['nombre_titularVc'] = $_POST['txt_nombre'];
					if ($tipo == 1) {
						$datos['amex']['nombre_titularVc'] = $_POST['txt_nombre'];
					}
				} else {
					$this->reg_errores['txt_nombre'] = 'Ingresa tu nombre correctamente';
				}
			}
			if (array_key_exists('txt_apellidoPaterno', $_POST)) {
				if(preg_match('/^[A-ZáéíóúÁÉÍÓÚÑñ \'.-]{1,30}$/i', $_POST['txt_apellidoPaterno'])) { 
					$datos['tc']['apellidoP_titularVc'] = $_POST['txt_apellidoPaterno'];
					if ($tipo == 1) {
						$datos['amex']['apellidoP_titularVc'] = $_POST['txt_apellidoPaterno'];
					}
				} else {
					$this->reg_errores['txt_apellidoPaterno'] = 'Ingresa tu apellido correctamente';
				}
			}
			if (array_key_exists('txt_apellidoMaterno', $_POST) && !empty($_POST['txt_apellidoMaterno'])) {
				if(preg_match('/^[A-ZáéíóúÁÉÍÓÚÑñ \'.-]{1,30}$/i', $_POST['txt_apellidoMaterno'])) {
					$datos['tc']['apellidoM_titularVc'] = $_POST['txt_apellidoMaterno'];
					if ($tipo == 1) {	//Amex
						$datos['amex']['apellidoM_titularVc'] = $_POST['txt_apellidoMaterno'];
					}
				} else {
					$this->reg_errores['txt_apellidoMaterno'] = 'Ingresa tu apellido correctamente';
				}
			} else {
				$datos['tc']['apellidoM_titularVc'] = "";
					if ($tipo == 1) {
						$datos['amex']['apellidoM_titularVc'] = "";
					}
			}
			/*
			if(array_key_exists('txt_codigoSeguridad', $_POST)) {
				//este código sólo se almaccena para solicitar el pago 
				$datos['codigo_seguridad'] = $_POST['txt_codigoSeguridad']; 
			}
			*/
			if (array_key_exists('sel_mes_expira', $_POST)) {
				$datos['tc']['mes_expiracionVc'] = $_POST['sel_mes_expira']; 
			}
			if (array_key_exists('sel_anio_expira', $_POST)) { 
				$datos['tc']['anio_expiracionVc'] = $_POST['sel_anio_expira'];  
			}
			
			if (array_key_exists('chk_guardar', $_POST)) {
				$datos['guardar'] = $_POST['chk_guardar'];		//indicador para saber si se guarda o no la tarjeta
				$datos['tc']['id_estatusSi'] = 1;
			}
			/*
			//tomando en cuenta la renovación automática
			$ra = $this->session->userdata('lleva_ra');
			//si lleva rs, se guarda la tarjeta
			if ($ra) {
				$datos['guardar'] = $_POST['chk_guardar'];
				$datos['tc']['id_estatusSi'] = 1;
			}
			*/			

			if (array_key_exists('chk_default', $_POST)) {
				$datos['tc']['id_estatusSi'] = 3;	//indica que será la tarjeta predeterminada
				$datos['predeterminar'] = true;	
			}
			
			//AMEX
			if (array_key_exists('txt_calle', $_POST)) {
				if(preg_match('/^[A-Z0-9 \'.-áéíóúÁÉÍÓÚÑñ]{2,40}$/i', $_POST['txt_calle'])) {
					$datos['amex']['calle'] = $_POST['txt_calle'];
				} else {
					$this->reg_errores['txt_calle'] = 'Ingresa tu calle y n&uacute;mero correctamente';
				}
			} /*else {
				$datos['amex']['calle'] = '';
			}*/
			if (array_key_exists('txt_cp', $_POST)) {
				//regex usada en js
				if(preg_match('/^([1-9]{2}|[0-9][1-9]|[1-9][0-9])[0-9]{3}$/', $_POST['txt_cp'])) {
					$datos['amex']['codigo_postal'] = $_POST['txt_cp'];
				} else {
					$this->reg_errores['txt_cp'] = 'Ingresa tu c&oacute;digo postal correctamente';
				}
			} /*else {
				$datos['amex']['codigo_postal'] = '';
			}*/
			if (array_key_exists('txt_ciudad', $_POST)) {
				if(preg_match('/^[A-Z0-9 \'.,-áéíóúÁÉÍÓÚÑñ]{2,40}$/i', $_POST['txt_ciudad'])) {
					$datos['amex']['ciudad'] = $_POST['txt_ciudad'];
				} else {
					$this->reg_errores['txt_ciudad'] = 'Ingresa tu ciudad correctamente';
				}
			} /*else {
				$datos['amex']['ciudad'] = '';
			}*/
			if (array_key_exists('txt_estado', $_POST)) {
				if(preg_match('/^[A-Z \'.-áéíóúÁÉÍÓÚÑñ]{2,40}$/i', $_POST['txt_estado'])) {
					$datos['amex']['estado'] = $_POST['txt_estado'];
				} else {
					$this->reg_errores['txt_estado'] = 'Ingresa tu estado correctamente';
				}
			} /*else {
				$datos['amex']['estado'] = '';
			}*/
			if (array_key_exists('txt_pais', $_POST)) {
				if(preg_match('/^[A-Z \'.-áéíóúÁÉÍÓÚÑñ]{2,40}$/i', $_POST['txt_pais'])) {
					$datos['amex']['pais'] = $_POST['txt_pais'];
				} else {
					$this->reg_errores['txt_pais'] = 'Ingresa tu pa&iacute;s correctamente';
				}
			} /*else {
				$datos['amex']['pais'] = '';
			}*/
			if (array_key_exists('sel_pais', $_POST)) {
				if ($_POST['sel_pais'] != "") {
					$datos['amex']['pais'] = $_POST['sel_pais'];
				} else {
					$this->reg_errores['sel_pais'] = 'Selecciona tu pa&iacute;s';
				}
			} /*else {
				$datos['amex']['pais'] = '';
			}*/
			if (array_key_exists('txt_email', $_POST) && trim($_POST['txt_email']) != "") {
				if(filter_var($_POST['txt_email'], FILTER_VALIDATE_EMAIL)) {
					$datos['amex']['mail'] = $_POST['txt_email'];
				} else {
					$this->reg_errores['txt_email'] = 'Ingresa tu email correctamente (opcional)';
				}
			} else {
				$datos['amex']['mail'] = '';
			}
			
			if (array_key_exists('txt_telefono', $_POST)) {
				if(preg_match('/^[0-9 -]{8,20}$/i', $_POST['txt_telefono'])) {
					$datos['amex']['telefono'] = $_POST['txt_telefono'];
				} else {
					$this->reg_errores['txt_telefono'] = 'Ingresa tu tel&eacute;fono correctamente';
				}
			} /*else {
				$datos['amex']['telefono'] = '';
			}*/

			if (array_key_exists('guardar_y_usar_otra', $_POST)) {
				$datos['redirect'] = FALSE;
			} else {
				$datos['redirect'] = TRUE;
			}
		} 
		
		//echo 'si no hay errores, $reg_errores esta vacio? '.empty($this->reg_errores).'<br/>';
		return $datos;
	}
	
	private function validar_tarjeta($tipo_tarjeta, $num_tarjeta) {
		$reg_visa 			= '/^4[0-9]{12}(?:[0-9]{3})?$/';
		$reg_master_card 	= '/^5[1-5][0-9]{14}$/';
		$reg_amex			= '/^3[47][0-9]{13}$/';
		//1 => Aamex, >1 => PROSA
		
		if ($tipo_tarjeta > 1 && !preg_match($reg_visa, $num_tarjeta) && !preg_match($reg_master_card, $num_tarjeta)) {
			return false;
		} else if ($tipo_tarjeta == 0 && !preg_match($reg_amex, $num_tarjeta)) {
			return false;
		} else if (!$this->validarLuhn($num_tarjeta)) {
			return false;
		} 
		
		return true;		//tarjeta válida
	}
	
	private function validarLuhn($num_tarjeta) {
		$num_card = array(16);
		$len = 0;
		$tarjeta_valida = false;
	
		//Obtener los dígitos de la tarjeta
		for ($i = 0; $i < strlen($num_tarjeta); $i++) {
			$num_card[$len++] = (int)($num_tarjeta[$i]);
		}
			
		//algoritmo Luhn
		$checksum = 0;
		for ($i = $len - 1; $i >= 0; $i--) {
			if ($i % 2 == $len % 2) {
				$n = $num_card[$i] * 2;
				$checksum += (int)($n / 10) + ($n % 10);
			} else {
				$checksum += $num_card[$i];
			}
		}
		
		$tarjeta_valida = ($checksum % 10 == 0);
		
		return $tarjeta_valida;
	}
	
	private function cargar_en_session($tarjeta = null)
	{
		//echo 'tarjeta: '.$tarjeta.'<br/> is int: ' . (int)($tarjeta) . '<br/>is array: ' . is_array($tarjeta) . '<br/>tipo: ' . gettype($tarjeta) . '<br/>';
		if (is_array($tarjeta)) { //si no se guarda en BD
			$this->session->set_userdata('tarjeta', $tarjeta);
		} else if ( ((int)$tarjeta) != 0 && is_int((int)$tarjeta)) {	//si ya está regiustrada la tarjeta en BD sólo sube el consecutivo
			$this->session->set_userdata('tarjeta', $tarjeta);
		} else {	//si no es ninguno de los dos, elimina el elemento de la sesión
			$this->session->unset_userdata('tarjeta');
		}
		//Por default se elimina el pago con depósito bancario
		$this->session->unset_userdata('deposito');
	}
	
	/*
	 * Carga una vista agragando los componentes separados de la misma
	 * */
	private function cargar_vista($folder, $page, $data)
	{	
		//Para automatizar un poco el desplieguee
		$this->load->view('templates/header', $data);
		$this->load->view('templates/menu.html', $data);
		if ($this->session->userdata('promociones') && $this->session->userdata('promocion')) {
			$data['detalle_promociones']=$this->api->obtiene_articulos_y_promociones();					
			$this->load->view('templates/promocion.html', $data);															
		}
		$this->load->view($folder.'/'.$page, $data);
		$this->load->view('templates/footer', $data);
		/*
		echo "<pre>";
			print_r($data);
		echo "</pre>";
		 */
		
	}
	
	/*
	 * Verifica la sesión del usuario
	 * 
	 * */
	private function redirect_cliente_invalido($revisar = 'id_cliente', $destino = 'login', $protocolo = 'http://') {
		if (!$this->session->userdata($revisar)) {
			//$url = $protocolo . BASE_URL . $destination; // Define the URL.
			$url = site_url($destino); // Define the URL.
			header("Location: $url");
			exit(); // Quit the script.
		}
	}
	/**
	 * Covierte recursivamente los objetos en arrays
	 */
	private function ArrayToObject($array){
      $obj= new stdClass();
      foreach ($array as $k=> $v) {
         if (is_array($v)){
            $v = ArrayToObject($v);   
         }
         $obj->{$k} = $v;
      }
      return $obj;
   }			
}

/* End of file forma_pago.php */
/* Location: ./application/controllers/forma_pago.php */
