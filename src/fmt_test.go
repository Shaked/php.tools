package src

import (
	"os/exec"
	"strconv"
	"testing"
)

func TestNoop(t *testing.T) {

}

func BenchmarkHeadMinus5(b *testing.B) {
	benchmarkFmt(5, b)
}

func BenchmarkHeadMinus4(b *testing.B) {
	benchmarkFmt(4, b)
}

func BenchmarkHeadMinus3(b *testing.B) {
	benchmarkFmt(3, b)
}

func BenchmarkHeadMinus2(b *testing.B) {
	benchmarkFmt(2, b)
}

func BenchmarkHeadMinus1(b *testing.B) {
	benchmarkFmt(1, b)
}

func BenchmarkHead(b *testing.B) {
	benchmarkFmt(0, b)
}

func benchmarkFmt(commits int, b *testing.B) {
	b.StopTimer()
	exec.Command("git", "checkout", "master").Output()
	exec.Command("git", "branch", "-D", "performance").Output()
	exec.Command("git", "checkout", "-b", "performance").Output()
	exec.Command("git", "reset", "--hard", "HEAD~"+strconv.Itoa(commits)).Output()
	out, err := exec.Command("git", "log", "--pretty=oneline", "-n1").Output()
	if err != nil {
		b.Fatal(err)
	}
	b.Logf("%s", out)

	b.StartTimer()
	for n := 0; n < b.N; n++ {
		exec.Command("php", "test.php", "-v").Output()
	}
	b.StopTimer()
	exec.Command("git", "checkout", "master").Output()
	exec.Command("git", "branch", "-D", "performance").Output()
	b.StartTimer()
}
