{{-- RQ21: modal "Registrar situación excepcional" de la pantalla de Verificación. La lógica está en verificacion/index.blade.php. --}}
<style>
    #modal-situacion .sit-nota { display: block; margin-top: 4px; font-size: 12px; color: var(--muted); }
    #sit-error { color: var(--danger); font-size: 13px; font-weight: 600; margin: 0; }
</style>

<dialog id="modal-situacion">
    <form id="form-situacion" autocomplete="off">
        <div class="m-head">
            <span class="ic" style="background:var(--warn-bg);color:var(--warn)"><svg class="i"><use href="#i-alert"/></svg></span>
            <div><b>Registrar situación excepcional</b><small id="sit-examen">—</small></div>
            <button type="button" class="icon-btn x" aria-label="Cerrar" onclick="this.closest('dialog').close()"><svg class="i"><use href="#i-x"/></svg></button>
        </div>
        <div class="m-body">
            <div class="field">
                <label for="sit-categoria">Categoría <span class="req">*</span></label>
                <select id="sit-categoria" required>
                    <option value="">Selecciona la categoría…</option>
                    @foreach (\App\Models\SituacionExcepcional::CATEGORIAS as $valor => $texto)
                        <option value="{{ $valor }}">{{ $texto }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="sit-estudiante">Estudiante <i id="sit-estudiante-nota">opcional</i></label>
                <input id="sit-estudiante" type="text" inputmode="numeric" maxlength="30" placeholder="Código universitario o cédula">
                <small class="sit-nota" id="sit-estudiante-nombre"></small>
            </div>

            <div class="field" id="sit-bloque-destino" style="display:none">
                <label for="sit-destino">Ambiente destino <span class="req">*</span></label>
                <select id="sit-destino">
                    <option value="">Selecciona el ambiente…</option>
                    @foreach ($ambientes as $ambiente)
                        <option value="{{ $ambiente->id }}">{{ $ambiente->nombre }}</option>
                    @endforeach
                </select>
                <small class="sit-nota">El ambiente de origen se toma del lugar donde está hoy el estudiante.</small>
            </div>

            <div class="field">
                <label for="sit-detalle">Detalle <span class="req">*</span> <i id="sit-cuenta">0 / 1000</i></label>
                <textarea id="sit-detalle" rows="3" maxlength="1000" required placeholder="Ej.: El carnet no tiene foto y el estudiante no aparece con su cédula."></textarea>
            </div>

            <p id="sit-error" style="display:none" role="alert"></p>
        </div>
        <div class="m-foot">
            <button type="button" class="btn ghost" onclick="this.closest('dialog').close()">Cancelar</button>
            <button type="submit" class="btn" id="sit-guardar" disabled>Guardar situación</button>
        </div>
    </form>
</dialog>
