<?php

class Reporte_model extends CI_Model {	
    function __construct(){        
        parent::__construct();		
    }    	
				
	function obtener_usuarios_fecha($fecha_inicio, $fecha_fin){
						
		$fini=$fecha_inicio;
		$ffin=$this->fecha_fin($fecha_fin);							
			
		$qry = "SELECT * FROM CMS_IntCliente 
		        WHERE fecha_registroDt>='$fini' and fecha_registroDt<'$ffin' ORDER BY fecha_registroDt ASC ";
		$res = $this->db->query($qry);			
		return $res;				 
	}	
	
	function obtener_compras_fecha($fecha_inicio, $fecha_fin){
		$fini=$fecha_inicio;
		$ffin=$this->fecha_fin($fecha_fin);			
			
		$qry = "SELECT * FROM CMS_IntCompra 
		        WHERE fecha_compraDt>='$fini' and fecha_compraDt<'$ffin' ORDER BY fecha_compraDt ASC ";
		$res = $this->db->query($qry);			
		return $res;
		
	} 
	
	function obtener_compras_cliente($id_cliente){		
		$qry = "SELECT * FROM CMS_IntCompra 
		        WHERE id_clienteIn=".$id_cliente;
		$res = $this->db->query($qry);		
		return $res;	
	}
	
	function obtener_cliente($id_cliente){
		$qry = "SELECT * FROM CMS_IntCliente 
		        WHERE id_clienteIn=".$id_cliente;
		$res = $this->db->query($qry);			
		return $res;	
	}
	
	function obtener_rel_dir_envio($id_compra, $id_cliente){		
		$qry = "SELECT * FROM CMS_RelCompraDireccion 
		        WHERE id_clienteIn=".$id_cliente." AND id_compraIn=".$id_compra." AND address_type=0";
		$res = $this->db->query($qry);			
		return $res;	
	}
	
	function obtener_facturacion($id_compra, $id_cliente){		
		$qry = "SELECT * FROM CMS_RelCompraDireccion 
		        WHERE id_clienteIn=".$id_cliente." AND id_compraIn=".$id_compra." AND address_type=1";
		$res = $this->db->query($qry);			
		return $res;	
	}
	
	function obtener_dir_facturacion($id_consecutivo, $id_cliente){		
		$qry = "SELECT * FROM CMS_IntDireccion 
		        WHERE id_clienteIn=".$id_cliente." AND id_consecutivoSi=".$id_consecutivo;
		$res = $this->db->query($qry);			
		return $res;	
	}
	
	function obtener_razon_social($id_rs){		
		$qry = "SELECT * FROM CMS_IntRazonSocial 
		        WHERE id_razonSocialIn=".$id_rs;
		$res = $this->db->query($qry);			
		return $res;	
	}
	
	function obtener_medio_pago($id_compra, $id_cliente){		
		$qry = "SELECT * FROM CMS_RelCompraPago 
		        WHERE id_clienteIn=".$id_cliente." AND id_compraIn=".$id_compra;
		$res = $this->db->query($qry);			
		return $res;	
	}
	
	function obtener_codigo_autorizacion($id_compra, $id_cliente){		
		$qry = "SELECT * FROM CMS_RelCompraPagoDetalleTC 
		        WHERE id_clienteIn=".$id_cliente." AND id_compraIn=".$id_compra."  ORDER BY fecha_registroTs DESC";
		$res = $this->db->query($qry);			
		return $res;	
	}
	
	function obtener_tc($id_cliente, $id_tc){
		$qry = "SELECT * FROM CMS_IntTC 
		        WHERE id_clienteIn=".$id_cliente." AND id_TCSi=".$id_tc;
		$res = $this->db->query($qry);			
		return $res;		
	}
	
	function obtener_promo_compra($id_compra, $id_cliente){		
		$qry = "SELECT * FROM CMS_RelCompraArticulo 
		        WHERE id_clienteIn=".$id_cliente." AND id_compraIn=".$id_compra;
		$res = $this->db->query($qry);
		if($res->num_rows()>0){			
			$id_promo = $res->row()->id_promocionIn;						
		}			
		else{
			$id_promo = 0; 
		}
		return $id_promo;	
		
	}
	
	function obtener_detalle_promo($id_promo){
		$qry = "SELECT * FROM CMS_IntPromocion 
		        WHERE id_promocionIn=".$id_promo;
		$res = $this->db->query($qry);	
		return $res;	
	}
	
	function obtener_articulos($id_promo){
		$qry = "SELECT * FROM CMS_IntArticulo 
		        WHERE id_promocionIn=".$id_promo;
		$res = $this->db->query($qry);	
		return $res;
	}
	
	function obtener_issue($issue_id){
		$res = $this->db->get_where('CMS_IntIssue', array('issue_id'=>$issue_id));
		return $res;
	}
	
	function obtener_detalle_think($id_compra, $id_cliente){
		$qry = "SELECT * FROM CMS_RelOrdenThink 
		        WHERE id_compraIn=".$id_compra." AND id_clienteIn=".$id_cliente;
		$res = $this->db->query($qry);	
		if($res->num_rows > 0){
			return $res;
		}
		else{
			return FALSE;
		}			
	}
	
	public function compras_cliente_id($id_cliente){
		$qry = "select CA.id_clienteIn, CD.id_transaccionBi, CA.id_compraIn,CA.id_articuloIn, CA.id_promocionIn , OT.orderhdr_id, OT.order_item_seq, OT.customer_id, -- CP.id_TCSi,
 A.oc_id,OC.nombreVc, A.order_code_id, A.source_code_id, A.tarifaDC, A.monedaVc, CA.fecha_registroTs, 
 CD.codigo_autorizacionVc, CD.fecha_registroTs as fecha2, A.taxableBi
from cms0mxdb.CMS_RelCompraArticulo CA
left outer join cms0mxdb.CMS_RelOrdenThink OT on CA.id_clienteIn = OT.id_clienteIn and CA.id_compraIn = OT.id_compraIn
and CA.id_promocionIn=OT.id_promocionIn
 ,cms0mxdb.CMS_IntArticulo A, cms0mxdb.CMS_RelCompraPagoDetalleTC CD, cms0mxdb.TND_CatOCThink OC
where CA.id_articuloIn=A.id_articuloIn
 and CA.id_promocionIn=A.id_promocionIn
 and CA.id_compraIn = CD.id_compraIn
 and CA.id_clienteIn = CD.id_clienteIn
 -- and CD.respuesta_bancoVc='approved'
 and A.oc_id = OC.oc_id
 -- and OT.customer_id=5227712 
 and CA.id_clienteIn=".$id_cliente."
 order by OT.customer_id, CA.id_compraIn,CA.id_articuloIn, OT.orderhdr_id;";
		$res = $this->db->query($qry);	
		return $res;
	}
	
	public function fecha_fin($fecha_fin){
		$dia=substr($fecha_fin,8,2);
    	$mes=substr($fecha_fin,5,2);
    	$ano=substr($fecha_fin,0,4);
		
		$fechafinal = mktime(0,0,0,$mes,$dia,$ano);		
		$str=strtotime('+1 day',$fechafinal);
		$ffin=mdate('%Y/%m/%d',$str);
		return $ffin;	
	}
			
}