<?php

namespace App\Http\Controllers;

use App\Http\Traits\ImageUploadTrait;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class ProductController extends Controller
{
    use ImageUploadTrait;
    public function index(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'products' => Product::all()
        ]);
    }

    public function show(int $pid): \Illuminate\Http\JsonResponse
    {
        $product = Product::find($pid);

        if (empty($product)) return response()->json([
            'message' => 'Produto não encontrado'
        ], 404);

        return response()->json([
            'product' => $product
        ]);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $request->validate([
                'name' => 'bail|required',
                'description' => 'bail|required|string|min:5|max:255',
                'image' => 'bail|required|image|mimes:jpeg,png,jpg|max:4096',
                'price'=> 'bail|required|numeric|min:0|max:99999.99',
                'cep'=> "bail|string|min:8|max:9|required_without:gps_lat,gps_long",
                'gps_lat'=> "bail|string|required_without:cep",
                'gps_long'=> "bail|string|required_without:cep",
            ]);

            $data = $request->all();

            DB::beginTransaction();

            try {
                $img = $request->file('image');
                if ($img) $data['image'] = url('') . '/storage/' .$this->imageUpload($img);
            } catch (\Exception $e){
                return response()->json([
                    'error' => $e->getMessage()
                ], 400);
            }

            if (!empty($request->gps_lat) && !empty($request->gps_long)) {
                $res = Http::get('https://nominatim.openstreetmap.org/reverse?lat='.$request->gps_lat.'&lon='.$request->gps_long.'&format=json')->json();
                $add = $res['address'];

                list($data['neighbourhood'], $data['city'], $data['state'], $data['cep']) = [
                    $add['suburb'] ?? null,
                    $add['city'] ?? null,
                    $add['state'] ?? null,
                    $add['postcode'] ?? null
                ];

                if(!empty($add['road'])) $data['address'] = $add['road'];
                if (!empty($add['residential'])) $data['address'] = $add['residential'];
            }

            if (!empty($request->cep)) {
                $data['cep'] = preg_replace('/[^0-9]/', '', $data['cep']);
                $res = Http::get('https://viacep.com.br/ws/'.$data['cep'].'/json/')->json();
                list($data['neighbourhood'], $data['city'], $data['state'], $data['address']) = [
                    $res['bairro'] ?? null,
                    $res['localidade'] ?? null,
                    $res['uf'] ?? null,
                    $res['logradouro']?? null
                ];
            }

            $product = Product::create($data);

            DB::commit();
            return response()->json([
                'product' => $product
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function olivia(Request $request): \Illuminate\Http\JsonResponse
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
                "content" => "Você é a Olívia, uma assistente chatbot que ajuda o usuário a comprar algo online usado no OLX. Quando o usuário dizer que quer comprar um produto ou uma categoria de produto, você precisa sugerir, inicialmente, 3 opções do mesmo produto ou categorias de produto em diferentes faixas de preço. Se o usuário perguntar a diferença entre alguns produtos você pode sair um pouco do seu papel e responder como quiser, mas não saia tanto do contexto. Se o usuário falar uma faixa de preço específica ou falar que prefere uma característica específica, você pode sugerir um produto ao invés de sugerir 3. Dê preferencia para utilizar os dados em que foi treinado, mas, em sua ausência, você tem permissão de usar dados inventados ou suas suposições."]], $arr);

        try {
            $result = OpenAI::chat()->create([
//                'model' => 'ft:gpt-3.5-turbo-0613:fourway::89hGfCo3',
                'model' => 'ft:gpt-3.5-turbo-0613:fourway::8C4KRMVp',
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

    public function oliver(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'name' => 'string',
        ]);

        $arr = [];

        $arr[] = [
            "role" => "user",
            "content" => 'quero vender um'.$data['name']
        ];

        $messages = array_merge(
            [[ "role" => "system",
                "content" => "Você é o Oliver, um assistente chatbot que ajuda o usuário a vender algo online usado no OLX. O usuário vai dizer o nome do produto que está vendendo. Você irá retornar, em um formato JSON, uma descrição e um preço sugeridos para o produto. Quando conseguir sugerir, retorne um status 200 no json, Quando não for possível sugerir um produto por algum motivo, retorne um status 400. você pode retornar um erro em json, com um status de erro e uma mensagem explicando o porque não foi possível sugerir dados para este produto ou texto inserido. Você não precisa ser tão rígido nas mensagens de erro."]], $arr);
        try {
            $result = OpenAI::chat()->create([
                'model' => 'ft:gpt-3.5-turbo-0613:fourway::8C4KRMVp',
                'messages' => $messages
            ]);

            $res = json_decode($result['choices'][0]['message']['content']);

            if ($res->status != 200){
                return response()->json([
                    'message' => $res->message
                ], $res->status);
            }

            return response()->json([
                'description' => $res->description,
                'price' => $res->price
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function tune(Request $request){
        $data = $request->all();

        try {
            $reader = new Xlsx();
            $spreadsheet = $reader->load($_FILES['filetoupload']['tmp_name']);
            $worksheet = $spreadsheet->getActiveSheet();
            $worksheet_arr = $worksheet->toArray();
            return $this->processArr($worksheet_arr, $data['selected_model']);
        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
            echo $e->getMessage();
        }
    }

    private function processArr(array $wk, $model = 'olivia')
    {
        $obj = '';

        if ($model == 'olivia'){
            for ($i = 0; $i < count($wk); $i += 3) {
                $tmp = [];
                $tmp['messages'] = [
                    [
                        "role" => "system",
                        "content" => "Você é a Olívia, uma assistente chatbot que ajuda o usuário a comprar algo online usado no OLX. O usuário vai dizer que quer comprar um produto ou uma categoria de produto, e você precisa me sugerir, inicialmente, 3 opções em diferentes faixas de preço e com características semelhantes. Se o usuário falar uma faixa de preço específica ou falar que prefere uma característica específica, você pode sugerir um produto ao invés de sugerir 3. Dê preferencia para utilizar os dados em que foi treinado, mas, em sua ausência, você tem permissão de usar dados inventados ou suas suposições."
                    ],
                    [
                        "role"=> "user",
                        "content"=> "Quero comprar " . $wk[$i][0]
                    ],
                    [
                        "role"=> "assistant",
                        "content"=> "Opção Econômica:
                    Produto: ".$wk[$i][0]."
                    Preço: R$ ".$wk[$i][1]."
                    Características: ".$wk[$i][2]."
                    Local: ".$wk[$i][3]."

                Opção Intermediária:
                    Produto: ".$wk[$i+1][0]."
                    Preço: R$ ".$wk[$i+1][1]."
                    Características: ".$wk[$i+1][2]."
                    Local: ".$wk[$i+1][3]."

                Opção Premium:
                    Produto: ".$wk[$i+2][0]."
                    Preço: R$ ".$wk[$i+2][1]."
                    Características: ".$wk[$i+2][2]."
                    Local: ".$wk[$i+2][3]
                    ]
                ];

                $obj .= json_encode((object) $tmp)."<br>";
            }

            $obj .= "<br>".json_encode([
                'messages' => [
                    [
                        "role" => "system",
                        "content" => "Você é a Olívia, uma assistente chatbot que ajuda o usuário a comprar algo online usado no OLX. O usuário vai dizer que quer comprar um produto ou uma categoria de produto, e você precisa me sugerir, inicialmente, 3 opções em diferentes faixas de preço e com características semelhantes. Se o usuário falar uma faixa de preço específica ou falar que prefere uma característica específica, você pode sugerir um produto ao invés de sugerir 3. Dê preferencia para utilizar os dados em que foi treinado, mas, em sua ausência, você tem permissão de usar dados inventados ou suas suposições."
                    ],
                    [
                        "role"=> "user",
                        "content"=> "Qual a diferença entre um toyotta corolla e um honda civic?"
                    ],
                    [
                        "role"=> "assistant",
                        "content"=> "Ambos são sedãs compactos populares, mas diferem em estilo, marca, design, desempenho e preço. A escolha depende de suas preferências e necessidades individuais."
                    ]
                ]
            ]);

            $obj .= "<br>".json_encode([
                    'messages' => [
                        [
                            "role" => "system",
                            "content" => "Você é a Olívia, uma assistente chatbot que ajuda o usuário a comprar algo online usado no OLX. O usuário vai dizer que quer comprar um produto ou uma categoria de produto, e você precisa me sugerir, inicialmente, 3 opções em diferentes faixas de preço e com características semelhantes. Se o usuário falar uma faixa de preço específica ou falar que prefere uma característica específica, você pode sugerir um produto ao invés de sugerir 3. Dê preferencia para utilizar os dados em que foi treinado, mas, em sua ausência, você tem permissão de usar dados inventados ou suas suposições."
                        ],
                        [
                            "role"=> "user",
                            "content"=> "Tenho 30 anos, devo comprar um relógio caro ou viver de forma simples"
                        ],
                        [
                            "role"=> "assistant",
                            "content"=> "A decisão depende de suas prioridades e situação financeira. Certifique-se de que a compra não prejudique suas finanças e reflita sobre o valor pessoal que um relógio caro tem para você."
                        ]
                    ]
                ]);
        }

        if ($model == 'oliver'){
            for ($i = 0; $i < count($wk); $i++) {
                $tmp = [];
                $tmp['messages'] = [
                    [
                        "role" => "system",
                        "content" => "Você é o Oliver, um assistente chatbot que ajuda o usuário a vender algo online usado no OLX. O usuário vai dizer o nome do produto que está vendendo. Você irá retornar, em um formato JSON, uma descrição e um preço sugeridos para o produto baseado em seus dados. Quando não for possível sugerir um produto por algum motivo, ou, o usuário digitar outra coisa na caixa de texto, que não seja um produto, você pode retornar um erro em json, com um status de erro e uma mensagem explicando o porque não foi possível sugerir dados para este produto ou texto inserido. Você não precisa ser tão rígido nas mensagens de erro."
                    ],
                    [
                        "role"=> "user",
                        "content"=> "Quero vender " . $wk[$i][0]
                    ],
                    [
                        "role"=> "assistant",
                        "content"=> $wk[$i][1]
                    ]
                ];

                $obj .= json_encode((object) $tmp)."<br>";
            }
        }

        echo ($obj);
    }
}
