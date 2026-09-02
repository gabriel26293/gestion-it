<?php
include 'db.php'; // Asegúrate de que este archivo tiene la conexión $conexion

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Consultamos los datos actuales del ticket
    $query = "SELECT asunto, descripcion FROM tickets WHERE id = $id";
    $result = $conexion->query($query);
    
    if ($result && $result->num_rows > 0) {
        $ticket = $result->fetch_assoc();
    } else {
        die("<div class='p-4 text-white'>Error: Ticket no encontrado.</div>");
    }
} else {
    die("<div class='p-4 text-white'>Error: ID no especificado.</div>");
}
?>

<div class="modal-header border-0 pb-0">
    <h5 class="modal-title fw-bold text-white" style="font-family: 'Orbitron'; letter-spacing: 1px;">
        EDITAR TICKET #<?php echo $id; ?>
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body pt-4">
    <form id="formEditarTicket">
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        
        <div class="mb-4">
            <label class="form-label text-secondary small fw-bold mb-2" style="letter-spacing: 1px;">TÍTULO / ASUNTO:</label>
            <input type="text" name="asunto" 
                   class="form-control bg-dark text-white border-white border-opacity-10 py-2 shadow-none" 
                   style="border-radius: 12px; background-color: #0f172a !important;"
                   value="<?php echo htmlspecialchars($ticket['asunto']); ?>" required>
        </div>
        
        <div class="mb-4">
            <label class="form-label text-secondary small fw-bold mb-2" style="letter-spacing: 1px;">DESCRIPCIÓN:</label>
            <textarea name="descripcion" 
                      class="form-control bg-dark text-white border-white border-opacity-10 py-2 shadow-none" 
                      style="border-radius: 12px; background-color: #0f172a !important;" 
                      rows="6" required><?php echo htmlspecialchars($ticket['descripcion']); ?></textarea>
        </div>
        
        <div class="d-flex gap-2 mt-2">
            <button type="button" onclick="ejecutarGuardado(<?php echo $id; ?>)" 
                    class="btn btn-info fw-bold w-100 py-2 text-uppercase" 
                    style="border-radius: 12px; letter-spacing: 1px; background-color: #38bdf8; border: none; color: #000;">
                Guardar Cambios
            </button>
            <button type="button" class="btn btn-outline-secondary w-100 py-2 border-opacity-25 text-uppercase" 
                    style="border-radius: 12px; color: #94a3b8;" 
                    data-bs-dismiss="modal">
                Cancelar
            </button>
        </div>
    </form>
</div>

<script>
/**
 * Envía los datos mediante AJAX a tickets_procesar.php
 */
function ejecutarGuardado(id) {
    const formElement = document.getElementById('formEditarTicket');
    const formData = new FormData(formElement);
    
    // Agregamos la acción que el procesador debe ejecutar
    formData.append('accion', 'editar_basico'); 

    fetch('tickets_procesar.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: '¡SISTEMA ACTUALIZADO!',
                text: 'Los cambios se guardaron correctamente.',
                background: '#1e293b',
                color: '#fff',
                confirmButtonColor: '#38bdf8',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                location.reload(); // Recarga la lista principal
            });
        } else {
            Swal.fire({ 
                icon: 'error', 
                title: 'ERROR EN PROCESO', 
                text: data.message || 'No se pudo actualizar el registro.', 
                background: '#1e293b', 
                color: '#fff' 
            });
        }
    })
    .catch(err => {
        console.error('Error:', err);
        Swal.fire({
            icon: 'error',
            title: 'ERROR DE COMUNICACIÓN',
            text: 'No se pudo conectar con el servidor.',
            background: '#1e293b',
            color: '#fff'
        });
    });
}
</script>