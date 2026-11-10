// C++17; UTF-8. Mỗi file là một chương trình độc lập.
#include <iostream>
#include <vector>
#include <string>
#include <sstream>
#include <algorithm>
#include <utility>
#include <cassert>
using namespace std;

string mang(const vector<int>& a) {
    ostringstream out; out << "[";
    for (size_t i = 0; i < a.size(); ++i) {
        if (i) out << ",";
        out << a[i];
    }
    out << "]"; return out.str();
}
int soKetQua = 0;
void inToHop(const vector<int>& chon) {
    cout << "KẾT QUẢ " << ++soKetQua << ": " << mang(chon) << '\n';
}
void sinhToHop(int v, int n, int k, vector<int>& chon) {

    if ((int)chon.size() == k) {

        inToHop(chon); return;
    }
    if (v > n) {

        return;
    }
    if (n - v + 1 < k - (int)chon.size()) {

        return;
    }
    chon.push_back(v);

    sinhToHop(v + 1, n, k, chon);
    chon.pop_back();

    sinhToHop(v + 1, n, k, chon);

}
int main() {

    // ĐẦU VÀO: 1 <= k <= n; dùng n nhỏ khi demo.
    int n = 4, k = 2;
    if (n < 1 || k < 1 || k > n) { cerr << "Cần 1 <= k <= n.\n"; return 1; }
    vector<int> chon;
    sinhToHop(1, n, k, chon);
    assert(chon.empty());
    cout << "TỔNG: " << soKetQua << " tổ hợp.\n";
}
