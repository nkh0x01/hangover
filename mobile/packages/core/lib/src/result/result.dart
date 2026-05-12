/// A minimal Result sum type. Use across repositories so callers can
/// pattern-match on success vs failure without throwing for expected
/// flow control (e.g. wrong OTP, declined card).
sealed class Result<S, F> {
  const Result();

  bool get isOk => this is Ok<S, F>;
  bool get isErr => this is Err<S, F>;
}

final class Ok<S, F> extends Result<S, F> {
  const Ok(this.value);
  final S value;
}

final class Err<S, F> extends Result<S, F> {
  const Err(this.failure);
  final F failure;
}
