<?php

namespace App\Livewire\General\Ordenes;

use App\Models\EgresosModel;
use App\Models\InventarioModel;
use App\Models\OrdenesModel;
use App\Models\SucursalesModel;
use DateTime;
use Livewire\Attributes\On;
use Livewire\Component;

class Egreso extends Component
{

    public $id;

    public $orden;

    public $numero_orden;

    public $egresos;

    public $precio_unitario_con_iva;

    public $stock_disponible;

    public $datos;

    public $fecha;

    public $nombre_sucursal;

    public $stock_transferencia;

    public $comision;

    public $listaEgresosAgregados = [];

    public $comentario;

    public $categoria_1;

    public $categoria_2;

    public function mount(int $id)
    {
        $this->id = $id;

        $orden = OrdenesModel::where('id', $this->id)->where('tipo_orden', 'egreso')->first();

        if ($orden) {

            $this->orden = $orden;

            $this->datos = json_decode($this->orden->datos);

            $this->comentario = $this->orden->comentarios;

            $date = new DateTime($this->datos->created_at);

            $formattedDate = $date->format('Y-m-d');

            $this->fecha = $formattedDate;

            $sucursal = SucursalesModel::find($this->orden->id_sucursal);

            $this->nombre_sucursal = $sucursal->nombre_sucursal;

            // Verifica si $this->orden->detalle no está vacío y es una cadena
            if (!empty($this->orden->detalle) && is_string($this->orden->detalle)) {
                $decodedData = json_decode($this->orden->detalle, true);

                // Verifica si json_decode no produjo un error y devolvió un array válido
                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedData)) {
                    $this->listaEgresosAgregados = $decodedData;
                } else {
                    // Inicializa la variable en vacío si hay un error en la decodificación JSON
                    $this->listaEgresosAgregados = [];
                }
            } else {
                // Inicializa la variable en vacío si los datos no son válidos o están vacíos
                $this->listaEgresosAgregados = [];
            }
        } else {
            // La orden no existe, lanza una excepción o redirige a otra página
            abort(404, 'Orden no encontrada');
        }
    }

    public function registrarComentario()
    {
        $this->orden->comentarios = $this->comentario;

        if ($this->orden->save()) {
            $message = 'Comentario registrado';
            $icon = 'success';
            $this->dispatch('mensajes', message: $message, icon: $icon, state: false);
        } else {
            $message = 'Error al registrar el comentario';
            $icon = 'error';
            $this->dispatch('mensajes', message: $message, icon: $icon, state: false);
        }
    }

    #[On('agregar')]
    public function agregar($id)
    {
        $this->egresos = null;
        $this->precio_unitario_con_iva = null;
        $this->stock_transferencia = null;
        $this->categoria_1 = null;
        $this->categoria_2 = null;

        $egreso = EgresosModel::find($id);

        if ($egreso) {

            $this->egresos = $egreso;
            $this->categoria_1 = $this->egresos->categoria_1;
            $this->categoria_2 = $this->egresos->categoria_2;
        }
    }

    public function agregarListaEgresos()
    {
        $nuevoEgreso = [
            'id_egreso' => $this->egresos->id,
            'categoria_1' => $this->egresos->categoria_1,
            'categoria_2' => $this->egresos->categoria_2,
            'cantidad_egreso' => $this->stock_transferencia,
            'precio_unitario_con_iva' => $this->precio_unitario_con_iva,
            'descripcion' => $this->egresos->descripcion_egreso,
            'total' => -1 * ($this->stock_transferencia * $this->precio_unitario_con_iva),
        ];

        $existe = false;

        // Iterar sobre la lista de egresos agregados
        foreach ($this->listaEgresosAgregados as &$egreso) {
            if ($egreso['id_egreso'] === $nuevoEgreso['id_egreso'] && $egreso['precio_unitario_con_iva'] === $nuevoEgreso['precio_unitario_con_iva']) {

                // Egreso ya existe, actualizar cantidad y total
                $egreso['cantidad_egreso'] += $nuevoEgreso['cantidad_egreso'];
                $egreso['total'] = -1 * ($egreso['cantidad_egreso'] * $egreso['precio_unitario_con_iva']);

                $detalle = json_decode(json_encode($this->listaEgresosAgregados));

                foreach ($detalle as $key => $val) {
                    if ($val->id_egreso === $egreso['id_egreso']) {
                        $val->cantidad_egreso = $egreso['cantidad_egreso'];
                        $val->total = $egreso['total'];
                    }
                }

                $this->orden->detalle = json_encode($detalle);

                $this->orden->save();

                $existe = true;
                $message = 'El egreso se agregó a la lista';
                $icon = 'success';
                $this->dispatch('mensajes', message: $message, icon: $icon, state: true);
                $this->dispatch('recargarComponente');
                break;
            }
        }

        // Si no se encontró el egreso, agregarlo a la lista
        if (!$existe) {

            $this->listaEgresosAgregados[] = $nuevoEgreso;
            $this->orden->detalle = json_encode($this->listaEgresosAgregados);
            $this->orden->save();

            $message = 'El egreso se agregó a la lista';
            $icon = 'success';
            $this->dispatch('mensajes', message: $message, icon: $icon, state: true);
            $this->dispatch('recargarComponente');
        }
    }

    public function eliminarEgresoLista($id, $index)
    {
        $this->listaEgresosAgregados = array_filter($this->listaEgresosAgregados, function ($egreso, $key) use ($id, $index) {
            if ($egreso['id_egreso'] === $id && $key === $index) {
                return false; // Elimina el egreso del array si coincide el id y el índice
            }
            return true; // Mantén el egreso en el array
        }, ARRAY_FILTER_USE_BOTH);

        // Actualiza el detalle en la orden
        $this->orden->detalle = json_encode(array_values($this->listaEgresosAgregados));
        $this->orden->save();
    }

    public function validarPrecioUnitario()
    {
        // Remover espacios al inicio y al final
        $this->precio_unitario_con_iva = trim($this->precio_unitario_con_iva);

        // Verificar si el valor es un entero válido
        if (!ctype_digit($this->precio_unitario_con_iva)) {
            $message = "Ingrese un precio válido";
            $elementId = 'precio_unitario_con_iva';
            $this->dispatch('estadoCampos', message: $message, elementId: $elementId);
            $this->precio_unitario_con_iva = null;
            return false;
        }

        // Verificar si el valor es menor o igual a cero
        if ($this->precio_unitario_con_iva <= 0) {
            $message = "El precio no puede ser cero o negativo";
            $elementId = 'precio_unitario_con_iva';
            $this->dispatch('estadoCampos', message: $message, elementId: $elementId);
            $this->precio_unitario_con_iva = null;
            return false;
        }

        return true;
    }

    public function validarCantidadAgregar()
    {
        // Remover espacios al inicio y al final
        $this->stock_transferencia = trim($this->stock_transferencia);

        // Verificar si el valor es un entero válido
        if (!ctype_digit($this->stock_transferencia)) {
            $message = "Ingrese una cantidad válida";
            $elementId = 'stock_transferencia';
            $this->dispatch('estadoCampos', message: $message, elementId: $elementId);
            $this->stock_transferencia = null;
            return false;
        }

        // Verificar si el valor es menor o igual a cero
        if ($this->stock_transferencia <= 0) {
            $message = "El cantidad no puede ser cero o negativa";
            $elementId = 'stock_transferencia';
            $this->dispatch('estadoCampos', message: $message, elementId: $elementId);
            $this->stock_transferencia = null;
            return false;
        }

        return true;
    }

    public function render()
    {
        return view('livewire.general.ordenes.egreso');
    }
}