<form action="/api/tune" method="post" enctype="multipart/form-data" style="font-family: 'Helvetica',serif; max-width: 400px; margin: 0 auto; padding: 20px; border: 1px solid #ccc; border-radius: 5px; background-color: #f9f9f9;">

    @csrf

    <div class="form-group" style="margin-bottom: 15px;">
        <label for="filetoupload" style="font-weight: bold; color: #333;">Selecione um arquivo base:</label>
        <input type="file" name="filetoupload" id="filetoupload" accept=".xlsx" style="border: 1px solid #ccc; border-radius: 5px; padding: 5px; width: 100%;">
        <small id="fileHelp" class="form-text text-muted">Por favor, escolha um arquivo no formato .csv no formato específico</small>
    </div>

    <div class="form-group" style="margin-bottom: 15px;">
        <label for="model" style="font-weight: bold; color: #333;">Escolha o modelo a ser utilizado:</label>
        <select id="model" name="selected_model" style="border: 1px solid #ccc; border-radius: 5px; padding: 5px; width: 100%;">
            <option value="oliver">Oliver</option>
            <option value="olivia">Olivia</option>
        </select>
    </div>

    <button type="submit" class="btn btn-primary" name="submit" style="background-color: #007bff; color: #fff; border: none; border-radius: 5px; padding: 10px 15px; cursor: pointer;">
        Enviar
    </button>
</form>
