<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use OpenAI\Laravel\Facades\OpenAI;

class ProductController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'products' => Product::all()
        ]);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required',
                'description' => 'required',
                'price' => 'required',
                'image' => 'required',
            ]);

            $product = Product::create($request->all());
            return response()->json([
                'product' => $product
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function msg(Request $request): \Illuminate\Http\JsonResponse
    {
        $dados = $request->validate([
            'message' => 'string',
            'arr' => 'array',
        ]);

        $arr = $dados['arr'];

        $arr[] = [
            "role" => "user",
            "content" => $dados['message']
        ];

        $messages = array_merge(
            [[ "role" => "system",
                "content" => "Você é a Olívia, uma assistente chatbot que ajuda o usuário a comprar algo online usado no OLX. O usuário vai dizer que quer comprar um produto ou uma categoria de produto, e você precisa me sugerir, inicialmente, 3 opções em diferentes faixas de preço e com características semelhantes. Se o usuário perguntar a diferença entre alguns produtos você pode sair do seu modelo padrão e responder como quiser. Se o usuário falar uma faixa de preço específica ou falar que prefere uma característica específica, você pode sugerir um produto ao invés de sugerir 3. Dê preferencia para utilizar os dados em que foi treinado, mas, em sua ausência, você tem permissão de usar dados inventados ou suas suposições."]], $arr);

        try {
            $result = OpenAI::chat()->create([
                'model' => 'ft:gpt-3.5-turbo-0613:fourway::89hGfCo3',
                'messages' => $messages
            ]);

            return response()->json([
                'role' => 'assistant',
                'message' => nl2br($result['choices'][0]['message']['content'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
