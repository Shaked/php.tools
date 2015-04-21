package main

import (
	"flag"
	"fmt"
	"log"
	"os"
	"os/exec"
	"strconv"
	"strings"
	"testing"
	"text/tabwriter"
)

func main() {
	flag.Parse()
	w := tabwriter.NewWriter(os.Stdout, 0, 8, 0, '\t', 0)
	for commits := 10; commits > 0; commits-- {
		exec.Command("git", "checkout", "master").Output()
		exec.Command("git", "branch", "-D", "performance").Output()
		exec.Command("git", "checkout", "-b", "performance").Output()
		exec.Command("git", "reset", "--hard", "HEAD~"+strconv.Itoa(commits)).Output()
		out, err := exec.Command("git", "log", "--pretty=oneline", "-n1").Output()
		if err != nil {
			log.Fatal(err)
		}
		fmt.Fprintf(w, "%s\t", strings.TrimSpace(string(out)))

		b := testing.Benchmark(func(b *testing.B) {
			for n := 0; n < b.N; n++ {
				exec.Command("php", "test.php").Output()
			}
		})

		fmt.Fprintln(
			w,
			b.N, "\t",
			b.T, "\t",
			averagePerExecution(b.T.Nanoseconds(), int64(b.N)),
		)

		exec.Command("git", "checkout", "master").Output()
		exec.Command("git", "branch", "-D", "performance").Output()
	}
	w.Flush()
}

func averagePerExecution(t, n int64) float64 {
	return float64(t/n) / float64(1e9)
}
