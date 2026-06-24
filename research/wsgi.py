from waitress import serve
from app import app

if __name__ == '__main__':
    print('A股投研工具站 启动在 5001 端口')
    serve(app, host='0.0.0.0', port=5001, threads=8)
