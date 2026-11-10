{{-- RQ20: modal "Expulsar estudiante" de la lista "Ingresaron" de Control en vivo. La lógica está en en_vivo.blade.php. --}}
<style>
    .ev-btn-exp { padding:6px 12px; border-radius:9px; border:1px solid #fecaca; background:#fff; color:#b91c1c; font-weight:700; cursor:pointer; }
    .ev-btn-exp:hover { background:#fee2e2; }
    .ev-sub { display:block; margin-top:3px; font-size:12px; font-weight:400; color:var(--muted); max-width:340px; }
</style>

<dialog id="modal-expulsion">
    <form id="form-expulsion" autocomplete="off">
        <div class="m-head">
            <span class="ic" style="background:var(--danger-bg);color:var(--danger)"><svg class="i"><use href="#i-alert"/></svg></span>
            <div><b>Expulsar estudiante</b><small id="exp-quien">—</small></div>
            <button type="button" class="icon-btn x" aria-label="Cerrar" onclick="this.closest('dialog').close()"><svg class="i"><use href="#i-x"/></svg></button>
        </div>
        <div class="m-body">
            <div class="field">
                <label for="exp-motivo">Motivo de la expulsión <span class="req">*</span> <i id="exp-cuenta">0 / 500</i></label>
                <textarea id="exp-motivo" rows="3" maxlength="500" required placeholder="Ej.: Usaba el celular durante el examen."></textarea>
            </div>
            <p id="exp-error" class="err" style="display:none" role="alert"></p>
        </div>
        <div class="m-foot">
            <button type="button" class="btn ghost" onclick="this.closest('dialog').close()">Cancelar</button>
            <button type="submit" class="btn" id="exp-confirmar" style="background:var(--danger)" disabled>Confirmar expulsión</button>
        </div>
    </form>
</dialog>
