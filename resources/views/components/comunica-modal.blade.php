<!-- Comunica Modal -->
<div id="comunicaModal" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header" style="background: #6f42c1;">
            <h3 style="margin: 0; color: white;">
                <i class="fas fa-bullhorn me-2"></i> COMUNICA
            </h3>
            <button type="button" class="modal-close" id="closeComunicaModal">&times;</button>
        </div>
        <div class="modal-body" style="padding: 25px;">
            <div class="form-group mb-4">
                <label style="font-weight: 600; margin-bottom: 10px; display: block; color: #333;">
                    <i class="fas fa-print me-2"></i> Seleziona Stampante
                </label>
                <select id="comunicaPrinterSelect" class="form-control" style="padding: 12px; font-size: 16px; border: 2px solid #dee2e6; border-radius: 4px;">
                    <option value="">-- Seleziona una stampante --</option>
                    @php
                        $printers = \App\Models\Printer::where('is_active', true)->orderBy('label')->get();
                    @endphp
                    @foreach($printers as $printer)
                        <option value="{{ $printer->id }}">{{ $printer->label }} ({{ $printer->ip }})</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group mb-4">
                <label style="font-weight: 600; margin-bottom: 10px; display: block; color: #333;">
                    <i class="fas fa-comment-alt me-2"></i> Messaggio
                </label>
                <textarea id="comunicaMessage" class="form-control" rows="4" placeholder="Inserisci il messaggio da stampare..." style="padding: 12px; font-size: 16px; border: 2px solid #dee2e6; border-radius: 4px; resize: vertical;"></textarea>
            </div>
            <div class="form-group">
                <label style="font-weight: 600; margin-bottom: 10px; display: block; color: #333;">
                    <i class="fas fa-chair me-2"></i> Tavolo (opzionale)
                </label>
                <input type="text" id="comunicaTableNumber" class="form-control" placeholder="Es: 5" style="padding: 12px; font-size: 16px; border: 2px solid #dee2e6; border-radius: 4px;" readonly>
            </div>
        </div>
        <div class="modal-footer" style="padding: 15px 25px; background: #f8f9fa; border-top: 2px solid #dee2e6;">
            <button type="button" class="btn" id="cancelComunica" style="background: #6c757d; color: white; padding: 12px 25px; border: none; border-radius: 4px; font-weight: 600; margin-right: 10px;">
                <i class="fas fa-times me-2"></i> ANNULLA
            </button>
            <button type="button" class="btn" id="confirmComunica" style="background: #6f42c1; color: white; padding: 12px 25px; border: none; border-radius: 4px; font-weight: 600;">
                <i class="fas fa-paper-plane me-2"></i> INVIA
            </button>
        </div>
    </div>
</div>

<style>
#comunicaModal .modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.8);
    z-index: 2000;
    display: flex;
    justify-content: center;
    align-items: center;
}

#comunicaModal.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.8);
    z-index: 2000;
    display: flex;
    justify-content: center;
    align-items: center;
}

#comunicaModal .modal-content {
    background: white;
    border-radius: 8px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    overflow: hidden;
    animation: modalSlideIn 0.3s ease-out;
}

#comunicaModal .modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 25px;
}

#comunicaModal .modal-close {
    background: transparent;
    border: none;
    color: white;
    font-size: 28px;
    cursor: pointer;
    padding: 0;
    line-height: 1;
}

#comunicaModal .modal-close:hover {
    opacity: 0.8;
}

#comunicaModal .form-control:focus {
    border-color: #6f42c1;
    outline: none;
    box-shadow: 0 0 0 3px rgba(111, 66, 193, 0.2);
}

#comunicaModal .btn:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

@keyframes modalSlideIn {
    from {
        transform: translateY(-30px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}
</style>
