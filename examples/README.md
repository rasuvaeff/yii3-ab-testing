# Examples

| Script | Shows | Needs server? |
|---|---|---|
| `basic-usage.php` | Config targeting, deterministic assignment, decision reasons, and the exposure → receipt → conversion flow | No |
| `log-shipping.php` | The log-shipping delivery path: canonical schema v2 rows on stdout, with the context allow-list applied | No |
| `vector.toml` | Collector side of the same path — Vector reading those records into ClickHouse | Yes (Vector + ClickHouse) |

## Running

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 php examples/basic-usage.php
docker run --rm -v "$PWD":/app -w /app composer:2 php examples/log-shipping.php
```

`vector.toml` is configuration, not a script. Point `include` at the file your
application logs to and run `vector --config examples/vector.toml`. The tables it
writes to are created by `rasuvaeff/yii3-ab-testing-clickhouse`, which owns the
analytics schema.
